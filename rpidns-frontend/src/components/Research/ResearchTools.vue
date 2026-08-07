<!-- (c) Vadim Pavlov 2020 - 2026 -->
<!--
  ResearchTools.vue
  Research tools panel: one target, one Analyze action, and a card grid that
  shows every tool result side by side.

  Layout rationale (replaces the previous stack of ten rows, each with its own
  Run button):
    - A single sticky command bar owns the target and the primary action, so
      there is exactly one "execute" control for the common case.
    - Tool selection and the optional custom DNS server live in a collapsed
      Options drawer, keeping the bar to one uncluttered line.
    - Results render as a responsive card grid so several tools can be read at
      the same time instead of scrolling a tall single column. Each card keeps a
      quiet icon-only re-run/copy affordance in its header.
    - Tools backed by JSON APIs (RDAP/WHOIS, reputation) are shown as the
      readable summary the backend derives, with a per-card switch to the raw
      JSON it came from. Both forms arrive in the same response, so toggling
      costs no extra request, and the switch only appears where both exist.
    - Targets are classified client-side (domain vs IP) exactly as the backend
      validators do, and only the tools the server will accept are rendered.
      Tools that cannot apply (PTR/GeoIP/ASN for a domain, TLS/NS-MX/preview for
      an IP) are omitted entirely rather than shown as cards reporting an error,
      with a single footnote naming what was left out.
-->
<template>
  <div class="research-tools">
    <!-- Command bar: target + primary action (single execute control) -->
    <div class="rt-bar">
      <BInputGroup size="sm" class="rt-target">
        <template #prepend>
          <BInputGroupText><i class="fas fa-crosshairs fa-fw"></i></BInputGroupText>
        </template>
        <BFormInput
          v-model="target"
          placeholder="Domain or IP — example.com or 1.2.3.4"
          maxlength="253"
          autocomplete="off"
          aria-label="Target domain or IP"
          @keyup.enter="runAll"
        ></BFormInput>
        <template #append>
          <BButton
            v-b-tooltip.hover
            title="Clear target"
            variant="outline-secondary"
            :disabled="!target"
            @click="target = ''"
          >
            <i class="fas fa-xmark"></i>
          </BButton>
        </template>
      </BInputGroup>

      <!-- Target classification: tells the user which tool set applies -->
      <span class="rt-kind" :class="`rt-kind--${targetKind}`">{{ kindLabel }}</span>

      <BButton
        variant="primary"
        size="sm"
        class="rt-run"
        :disabled="!canRun"
        @click="runAll"
      >
        <BSpinner v-if="anyRunning" small></BSpinner>
        <i v-else class="fas fa-play"></i>&nbsp;Analyze
      </BButton>

      <BButton
        v-b-tooltip.hover
        title="Options: tool selection and DNS server"
        :variant="showOptions ? 'secondary' : 'outline-secondary'"
        size="sm"
        @click="showOptions = !showOptions"
      >
        <i class="fas fa-sliders"></i>
      </BButton>
    </div>

    <!-- Options drawer: which tools to run + optional DNS server -->
    <div v-if="showOptions" class="rt-options">
      <BRow class="gy-2">
        <BCol cols="12" lg="8">
          <div class="rt-options__label">
            Tools to run
            <BButton variant="link" size="sm" class="rt-linkbtn" @click="selectAllTools(true)">all</BButton>
            <span class="text-muted">/</span>
            <BButton variant="link" size="sm" class="rt-linkbtn" @click="selectAllTools(false)">none</BButton>
          </div>
          <div class="rt-chips">
            <BFormCheckbox
              v-for="tool in optionTools"
              :key="tool.name"
              v-model="selected[tool.name]"
              inline
              size="sm"
            >{{ tool.label }}</BFormCheckbox>
          </div>
        </BCol>
        <BCol cols="12" lg="4">
          <div class="rt-options__label">
            DNS resolver <span class="text-muted">(all tools)</span>
          </div>
          <BInputGroup size="sm">
            <template #prepend>
              <BInputGroupText><i class="fas fa-server fa-fw"></i></BInputGroupText>
            </template>
            <BFormInput
              v-model="dnsServer"
              :placeholder="dnsPlaceholder"
              maxlength="253"
              autocomplete="off"
              aria-label="DNS resolver for this run"
            ></BFormInput>
            <template #append>
              <BButton
                v-b-tooltip.hover
                :title="`Restore the configured default (${defaultDnsLabel})`"
                variant="outline-secondary"
                :disabled="dnsIsDefault"
                @click="resetDnsServer"
              >
                <i class="fas fa-rotate-left"></i>
              </BButton>
            </template>
          </BInputGroup>
          <div class="rt-options__hint">{{ dnsHint }}</div>
        </BCol>
      </BRow>
    </div>

    <!-- Empty state: nothing to show until a target is entered -->
    <div v-if="targetKind === 'empty'" class="rt-empty">
      <i class="fas fa-toolbox fa-2x"></i>
      <p class="mb-0 mt-2">Enter a domain or IP address, then press Analyze.</p>
      <p class="text-muted small mb-0">
        Every applicable tool runs against the same target and its result appears below.
      </p>
    </div>
    <BAlert v-else-if="targetKind === 'invalid'" :model-value="true" variant="warning" class="rt-invalid py-2 px-3">
      <i class="fas fa-triangle-exclamation"></i>&nbsp;
      "{{ target }}" is neither a valid domain name nor a valid IP address.
    </BAlert>

    <!-- Result grid: every tool that applies to this target, all visible at once -->
    <div v-else class="rt-grid">
      <section
        v-for="tool in visibleTools"
        :key="tool.name"
        class="rt-card"
        :class="{ 'rt-card--wide': isWide(tool) }"
      >
        <header class="rt-card__head">
          <span class="rt-card__title">
            <i class="fas fa-fw" :class="tool.icon"></i>&nbsp;{{ tool.label }}
          </span>
          <span class="rt-card__tools">
            <BSpinner v-if="results[tool.name].loading" small></BSpinner>
            <i
              v-else-if="results[tool.name].error"
              v-b-tooltip.hover
              :title="results[tool.name].error"
              class="fas fa-circle-exclamation text-danger"
            ></i>
            <i
              v-else-if="results[tool.name].exitError"
              v-b-tooltip.hover
              title="The tool exited with an error"
              class="fas fa-circle-exclamation text-warning"
            ></i>
            <i v-else-if="hasResult(tool)" class="fas fa-circle-check text-success"></i>

            <!-- Summary / JSON switch, only for results that have both forms -->
            <button
              v-if="results[tool.name].raw"
              v-b-tooltip.hover
              :title="results[tool.name].showRaw ? 'Show readable summary' : 'Show raw JSON'"
              type="button"
              class="rt-iconbtn"
              :class="{ 'rt-iconbtn--on': results[tool.name].showRaw }"
              @click="toggleRaw(tool)"
            >
              <i class="fas" :class="results[tool.name].showRaw ? 'fa-list' : 'fa-code'"></i>
            </button>

            <button
              v-if="displayedOutput(tool)"
              v-b-tooltip.hover
              title="Copy output"
              type="button"
              class="rt-iconbtn"
              @click="copyOutput(tool)"
            >
              <i class="fas" :class="copied === tool.name ? 'fa-check' : 'fa-copy'"></i>
            </button>
            <button
              v-b-tooltip.hover
              :title="`Run ${tool.label}`"
              type="button"
              class="rt-iconbtn"
              :disabled="results[tool.name].loading"
              @click="runTool(tool.name)"
            >
              <i class="fas fa-rotate-right"></i>
            </button>
          </span>
        </header>

        <div class="rt-card__body">
          <!-- Running -->
          <p v-if="results[tool.name].loading" class="rt-note mb-0">
            <BSpinner small></BSpinner>&nbsp;&nbsp;Running...
          </p>

          <!-- Request-level failure -->
          <p v-else-if="results[tool.name].error" class="rt-note rt-note--error mb-0">
            {{ results[tool.name].error }}
          </p>

          <!-- Image result (website preview) -->
          <template v-else-if="tool.render === 'image'">
            <img
              v-if="results[tool.name].image"
              :src="`data:image/png;base64,${results[tool.name].image}`"
              :alt="`Website preview of ${target}`"
              class="rt-preview"
            />
            <p v-else-if="results[tool.name].ran" class="rt-note mb-0">
              {{ results[tool.name].reason || 'No preview available.' }}
            </p>
            <p v-else class="rt-note mb-0">Not run yet.</p>
          </template>

          <!-- Text result: the readable summary, or the JSON it came from -->
          <template v-else-if="results[tool.name].output !== null">
            <pre class="rt-output mb-0">{{ displayedOutput(tool) || '(no output)' }}</pre>
            <p v-if="results[tool.name].truncated" class="rt-note mb-0 mt-1">
              <i class="fas fa-scissors"></i>&nbsp;Output truncated.
            </p>
          </template>

          <p v-else class="rt-note mb-0">Not run yet.</p>
        </div>
      </section>

      <!-- Tools that do not apply to this target class are not rendered at all;
           this one-line note explains the gap so a missing card never looks
           like a bug. -->
      <p v-if="hiddenTools.length > 0" class="rt-hidden">
        Hidden for a {{ kindLabel }} target: {{ hiddenTools.map(t => t.label).join(', ') }}.
      </p>
    </div>
  </div>
</template>

<script>
import { ref, reactive, computed, watch, nextTick } from 'vue'
import { useApi } from '../../composables/useApi'
import {
  RESEARCH_TOOLS,
  DNS_AWARE_TOOLS,
  classifyTarget,
  toolAccepts,
  toolsForTarget,
  TARGET_DOMAIN,
  TARGET_IP,
  TARGET_EMPTY
} from '../../composables/useNetworkTools'

// How many tools may be in flight at once. Each request occupies one PHP-FPM
// worker for up to 30s, so the fan-out is capped to keep the appliance
// responsive while still filling the grid quickly.
const MAX_CONCURRENT = 3

// Tools whose output is wide or tall enough to deserve a full-width card.
const WIDE_TOOLS = ['traceroute', 'rdap', 'tls_cert', 'website_preview']

const freshState = () => ({
  loading: false,
  error: null,
  output: null,
  // Pretty-printed JSON behind `output` for the tools whose upstream is a JSON
  // API (RDAP, reputation, ...). Null when the backend has no separate JSON
  // view to offer, which is what hides the summary/JSON toggle.
  raw: null,
  showRaw: false,
  image: null,
  reason: null,
  truncated: false,
  exitError: false,
  ran: false
})

export default {
  name: 'ResearchTools',
  setup(props, { expose }) {
    const { get, post } = useApi()

    const target = ref('')
    const dnsServer = ref('')
    // Appliance-wide default from Admin > Settings. The field above starts out
    // holding it, and the reset control puts it back after an override.
    const defaultDnsServer = ref('')
    const showOptions = ref(false)
    const copied = ref(null)

    const tools = RESEARCH_TOOLS

    // Per-tool result state, keyed by tool name.
    const results = reactive({})
    // Which tools take part in "Analyze". All on by default so a single click
    // gives the full picture; unchecking is how a user skips the slow ones.
    const selected = reactive({})
    for (const tool of tools) {
      results[tool.name] = freshState()
      selected[tool.name] = true
    }

    // Load the appliance defaults once and seed the resolver field with the
    // configured value. Runs are gated on this promise so a tool launched
    // straight from a context menu still inherits the default instead of
    // racing the request and silently using the appliance resolver.
    const configReady = (async () => {
      try {
        const resp = await get({ req: 'research_config' })
        if (resp && resp.status === 'ok' && resp.data) {
          defaultDnsServer.value = resp.data.dns_server || ''
          // Only seed an untouched field, so a value typed while this was in
          // flight is never overwritten.
          if (dnsServer.value === '') dnsServer.value = defaultDnsServer.value
        }
      } catch (e) {
        // No defaults available: the field stays empty, which means the
        // appliance resolver, exactly as before this setting existed.
      }
    })()

    const targetKind = computed(() => classifyTarget(target.value))

    const defaultDnsLabel = computed(() => defaultDnsServer.value || 'appliance resolver')

    const dnsPlaceholder = computed(() =>
      defaultDnsServer.value
        ? `default: ${defaultDnsServer.value}`
        : 'default: appliance resolver'
    )

    const dnsIsDefault = computed(() => dnsServer.value.trim() === defaultDnsServer.value)

    const dnsHint = computed(() => {
      if (dnsServer.value.trim() === '') {
        return 'Appliance resolver: domains blocked here stay blocked.'
      }
      return dnsIsDefault.value
        ? 'Inherited from Admin > Settings.'
        : `Overrides the configured default (${defaultDnsLabel.value}) for this panel.`
    })

    const resetDnsServer = () => { dnsServer.value = defaultDnsServer.value }

    const kindLabel = computed(() => {
      if (targetKind.value === TARGET_DOMAIN) return 'domain'
      if (targetKind.value === TARGET_IP) return 'IP'
      if (targetKind.value === TARGET_EMPTY) return 'no target'
      return 'invalid'
    })

    const applicable = (tool) => toolAccepts(tool, targetKind.value)

    const isWide = (tool) => WIDE_TOOLS.includes(tool.name)

    // Only tools the backend will accept for this target class get a card. A
    // domain hides the IP-only tools (reverse DNS, GeoIP, ASN) and an IP hides
    // the domain-only ones, so no card ever renders just to say it cannot run.
    const visibleTools = computed(() => toolsForTarget(targetKind.value))

    // Counterpart of visibleTools, used for the one-line explanation of what is
    // not shown for the current target class.
    const hiddenTools = computed(() => tools.filter(tool => !applicable(tool)))

    // The Options drawer offers the same set that will actually run. With no
    // usable target yet it lists everything, so selections can be made upfront.
    const optionTools = computed(() =>
      (visibleTools.value.length > 0 ? visibleTools.value : tools)
    )

    const hasResult = (tool) => results[tool.name].ran

    // What the card body shows: the readable summary by default, the raw JSON
    // while the per-card toggle is on. Copy follows whatever is on screen.
    const displayedOutput = (tool) => {
      const state = results[tool.name]
      if (!state) return ''
      return (state.showRaw && state.raw) ? state.raw : state.output
    }

    const toggleRaw = (tool) => {
      const state = results[tool.name]
      if (!state || !state.raw) return
      state.showRaw = !state.showRaw
      // The copy confirmation refers to the previous view, so retract it.
      if (copied.value === tool.name) copied.value = null
    }

    const anyRunning = computed(() => tools.some(tool => results[tool.name].loading))

    const plannedTools = computed(() =>
      toolsForTarget(targetKind.value).filter(tool => selected[tool.name])
    )

    const canRun = computed(() => plannedTools.value.length > 0 && !anyRunning.value)

    // Clear every result so output from a previous target is never shown next to
    // a new one.
    const resetResults = () => {
      for (const tool of tools) {
        Object.assign(results[tool.name], freshState())
      }
      copied.value = null
    }

    watch(target, () => { resetResults() })

    // Execute one tool through the research_tool endpoint. Image-rendering tools
    // (website preview) return `{ image, reason }` instead of a ToolResult.
    const runTool = async (toolName) => {
      const tool = tools.find(t => t.name === toolName)
      const state = results[toolName]
      if (!tool || !state || state.loading) return
      if (!applicable(tool)) return
      // The configured resolver must be known before the first request goes out.
      await configReady

      // A re-run keeps the card's chosen view (summary vs JSON); it falls back
      // to the summary on its own if the new result carries no JSON.
      Object.assign(state, freshState(), { loading: true, showRaw: state.showRaw })

      const body = { tool: toolName, target: target.value.trim() }
      if (DNS_AWARE_TOOLS.includes(toolName)) {
        // Always sent, empty included: an empty value is how the panel says "use
        // the appliance resolver" rather than "fall back to the configured
        // default". Every tool honors it, so this covers the whole set.
        body.dns_server = dnsServer.value.trim()
      }

      try {
        const resp = await post({ req: 'research_tool' }, body)
        if (resp && resp.status === 'ok') {
          const data = resp.data || {}
          if (tool.render === 'image') {
            state.image = data.image || null
            state.reason = data.reason || null
          } else {
            state.output = data.output != null ? data.output : ''
            state.raw = data.raw != null && data.raw !== '' ? String(data.raw) : null
            state.truncated = !!data.truncated
            state.exitError = !!data.exitError
          }
        } else {
          state.error = (resp && resp.reason) || 'Tool execution failed'
        }
      } catch (e) {
        state.error = e && e.message ? e.message : 'Tool execution failed'
      } finally {
        state.loading = false
        state.ran = true
      }
    }

    // Run every selected, applicable tool with a bounded fan-out. Fast tools are
    // dispatched before the slow ones so the grid fills top-down.
    const runAll = async () => {
      if (!canRun.value) return
      const queue = plannedTools.value
        .slice()
        .sort((a, b) => (a.slow ? 1 : 0) - (b.slow ? 1 : 0))
        .map(tool => tool.name)

      const worker = async () => {
        while (queue.length > 0) {
          await runTool(queue.shift())
        }
      }
      const workers = []
      for (let i = 0; i < Math.min(MAX_CONCURRENT, queue.length); i++) {
        workers.push(worker())
      }
      await Promise.all(workers)
    }

    const selectAllTools = (value) => {
      for (const tool of tools) selected[tool.name] = value
    }

    const copyOutput = async (tool) => {
      const text = displayedOutput(tool)
      if (!text) return
      try {
        await navigator.clipboard.writeText(text)
        copied.value = tool.name
        setTimeout(() => { if (copied.value === tool.name) copied.value = null }, 1500)
      } catch (e) {
        // Clipboard access denied: nothing to do, the output stays selectable.
      }
    }

    // Programmatic entry point used by ToolsModal when a target is picked from a
    // log context menu: prefill the target and analyze it immediately. A named
    // tool runs just that tool; anything else runs the full applicable set.
    const runWith = async (newTarget, toolName) => {
      target.value = String(newTarget || '')
      await nextTick()
      if (toolName && results[toolName]) {
        await runTool(toolName)
      } else {
        await runAll()
      }
    }

    expose({ runWith })

    return {
      target,
      dnsServer,
      defaultDnsServer,
      defaultDnsLabel,
      dnsPlaceholder,
      dnsIsDefault,
      dnsHint,
      resetDnsServer,
      showOptions,
      copied,
      tools,
      results,
      selected,
      targetKind,
      kindLabel,
      visibleTools,
      hiddenTools,
      optionTools,
      isWide,
      hasResult,
      displayedOutput,
      toggleRaw,
      anyRunning,
      canRun,
      runTool,
      runAll,
      selectAllTools,
      copyOutput,
      runWith
    }
  }
}
</script>

<style scoped>
/* --- Command bar: one line, stays in view while results scroll --- */
.rt-bar {
  position: sticky;
  top: 0;
  z-index: 3;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 0;
  background-color: var(--bs-body-bg, #fff);
}

.rt-target { flex: 1 1 auto; min-width: 12rem; }
.rt-run { white-space: nowrap; }

.rt-kind {
  flex: 0 0 auto;
  font-size: 0.7rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  padding: 0.15rem 0.45rem;
  border-radius: 999px;
  border: 1px solid transparent;
}
.rt-kind--domain { color: #0a7d54; border-color: #0a7d54; }
.rt-kind--ip { color: #1d6fa5; border-color: #1d6fa5; }
.rt-kind--empty { color: #888; border-color: #ccc; }
.rt-kind--invalid { color: #a5321d; border-color: #a5321d; }

/* --- Options drawer --- */
.rt-options {
  border: 1px solid var(--bs-border-color, #dee2e6);
  border-radius: 0.375rem;
  padding: 0.5rem 0.75rem;
  margin-bottom: 0.75rem;
  background-color: rgba(0, 0, 0, 0.02);
}
.rt-options__label {
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: #6c757d;
  margin-bottom: 0.25rem;
}
.rt-chips { display: flex; flex-wrap: wrap; gap: 0.15rem 0.75rem; }
.rt-options__hint { font-size: 0.72rem; color: #6c757d; margin-top: 0.2rem; }
.rt-linkbtn { padding: 0 0.15rem; font-size: 0.75rem; vertical-align: baseline; }

/* --- Empty / invalid states --- */
.rt-empty {
  text-align: center;
  color: #6c757d;
  padding: 3rem 1rem;
}
.rt-invalid { margin-top: 0.25rem; }

/* --- Result grid: several tools readable at once --- */
.rt-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(22rem, 1fr));
  gap: 0.75rem;
  align-items: start;
}
.rt-card {
  border: 1px solid var(--bs-border-color, #dee2e6);
  border-radius: 0.375rem;
  overflow: hidden;
  min-width: 0;
}
/* Wide output (traceroute, RDAP, certificates, preview) gets the full row. */
@media (min-width: 992px) {
  .rt-card--wide { grid-column: 1 / -1; }
}
/* Footnote listing the tools that do not apply to the current target class. */
.rt-hidden {
  grid-column: 1 / -1;
  margin: 0;
  font-size: 0.75rem;
  color: #8a9199;
}

.rt-card__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
  padding: 0.35rem 0.5rem;
  background-color: rgba(0, 0, 0, 0.03);
  border-bottom: 1px solid var(--bs-border-color, #dee2e6);
}
.rt-card__title {
  font-weight: 600;
  font-size: 0.85rem;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.rt-card__tools {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  flex: 0 0 auto;
}

/* Quiet, icon-only card affordances instead of a wall of Run buttons. */
.rt-iconbtn {
  border: 0;
  background: transparent;
  color: #6c757d;
  padding: 0.1rem 0.25rem;
  line-height: 1;
  border-radius: 0.2rem;
  cursor: pointer;
}
.rt-iconbtn:hover:not(:disabled) { color: #212529; background-color: rgba(0, 0, 0, 0.06); }
.rt-iconbtn:disabled { opacity: 0.4; cursor: default; }
/* Active state for the summary/JSON switch, so the current view is evident. */
.rt-iconbtn--on { color: #212529; background-color: rgba(0, 0, 0, 0.1); }

.rt-card__body { padding: 0.5rem; }
.rt-note { font-size: 0.8rem; color: #6c757d; }
.rt-note--error { color: #a5321d; }

.rt-output {
  margin: 0;
  padding: 0.5rem;
  max-height: 20rem;
  overflow: auto;
  background-color: #1e1e1e;
  color: #e8e8e8;
  border-radius: 0.25rem;
  font-size: 0.75rem;
  line-height: 1.35;
  white-space: pre-wrap;
  word-break: break-word;
}
.rt-preview {
  display: block;
  max-width: 100%;
  height: auto;
  border-radius: 0.25rem;
}
</style>
