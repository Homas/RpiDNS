<!-- (c) Vadim Pavlov 2020 - 2026 -->
<!--
  ResearchTools.vue
  Network research tools section of the Research page.
  Provides a shared target input plus per-tool controls for RDAP/WHOIS, dig
  (with an optional DNS-server field), ping, traceroute, and the additional
  threat-hunting tools (reverse DNS, NS/MX, GeoIP, ASN, TLS certificate,
  reputation, website-preview, bulk analysis).
  Each text tool renders its output in a <pre> region with a loading indicator
  (Req 6.10) and an error state (Req 6.12); output is shown on completion
  (Req 6.11). Website-preview renders the returned base64 image (Req 8.7) and
  bulk analysis accepts a textarea of up to 100 items (Req 8.8, 8.9).
  All tool execution is driven through the research_tool endpoint.
-->
<template>
  <div>
    <BCard class="h-100">
      <template #header>
        <span class="bold"><i class="fas fa-toolbox"></i>&nbsp;&nbsp;Research tools</span>
      </template>

      <!-- Shared target input (domain or IP) -->
      <BRow class="mb-3">
        <BCol cols="12" lg="8">
          <BFormGroup label="Target (domain or IP)" label-cols-sm="3" label-size="sm">
            <BInputGroup size="sm">
              <template #prepend>
                <BInputGroupText><i class="fas fa-crosshairs fa-fw"></i></BInputGroupText>
              </template>
              <BFormInput
                v-model="target"
                placeholder="example.com or 1.2.3.4"
                size="sm"
                maxlength="253"
                @keyup.enter="runTool('rdap')"
              ></BFormInput>
              <template #append>
                <BButton size="sm" :disabled="!target" @click="target = ''">Clear</BButton>
              </template>
            </BInputGroup>
          </BFormGroup>
        </BCol>
        <BCol cols="12" lg="4">
          <BFormGroup label="DNS server (dig)" label-cols-sm="4" label-size="sm">
            <BInputGroup size="sm">
              <template #prepend>
                <BInputGroupText><i class="fas fa-server fa-fw"></i></BInputGroupText>
              </template>
              <BFormInput
                v-model="dnsServer"
                placeholder="optional (default appliance)"
                size="sm"
              ></BFormInput>
            </BInputGroup>
          </BFormGroup>
        </BCol>
      </BRow>

      <!-- Text-output tools: per-tool button + <pre> output region -->
      <div
        v-for="tool in textTools"
        :key="tool.name"
        class="tool-block border rounded p-2 mb-2"
      >
        <div class="d-flex align-items-center justify-content-between mb-1">
          <span class="bold"><i class="fas fa-fw" :class="tool.icon"></i>&nbsp;{{ tool.label }}</span>
          <BButton
            variant="outline-secondary"
            size="sm"
            :disabled="!target || results[tool.name].loading"
            @click="runTool(tool.name)"
          >
            <BSpinner v-if="results[tool.name].loading" small></BSpinner>
            <i v-else class="fa fa-play"></i>&nbsp;Run
          </BButton>
        </div>

        <!-- Loading indication (Req 6.10) -->
        <div v-if="results[tool.name].loading" class="text-muted small">
          <BSpinner small></BSpinner>&nbsp;&nbsp;Running {{ tool.label }}...
        </div>

        <!-- Error state (Req 6.12) -->
        <BAlert v-else-if="results[tool.name].error" :model-value="true" variant="danger" class="py-1 px-2 mb-0 small">
          <i class="fas fa-triangle-exclamation"></i>&nbsp;{{ results[tool.name].error }}
        </BAlert>

        <!-- Tool output on completion (Req 6.11) -->
        <template v-else-if="results[tool.name].output !== null">
          <BAlert
            v-if="results[tool.name].exitError"
            :model-value="true"
            variant="warning"
            class="py-1 px-2 mb-1 small"
          >
            <i class="fas fa-circle-exclamation"></i>&nbsp;Tool exited with an error.
          </BAlert>
          <pre class="tool-output mb-0">{{ results[tool.name].output }}</pre>
          <div v-if="results[tool.name].truncated" class="text-muted small mt-1">
            <i class="fas fa-scissors"></i>&nbsp;Output truncated.
          </div>
        </template>
      </div>

      <!-- Website preview tool (renders returned base64 image) -->
      <div class="tool-block border rounded p-2 mb-2">
        <div class="d-flex align-items-center justify-content-between mb-1">
          <span class="bold"><i class="fas fa-image fa-fw"></i>&nbsp;Website preview</span>
          <BButton
            variant="outline-secondary"
            size="sm"
            :disabled="!target || preview.loading"
            @click="runPreview"
          >
            <BSpinner v-if="preview.loading" small></BSpinner>
            <i v-else class="fa fa-camera"></i>&nbsp;Capture
          </BButton>
        </div>

        <div v-if="preview.loading" class="text-muted small">
          <BSpinner small></BSpinner>&nbsp;&nbsp;Capturing preview...
        </div>
        <BAlert v-else-if="preview.error" :model-value="true" variant="danger" class="py-1 px-2 mb-0 small">
          <i class="fas fa-triangle-exclamation"></i>&nbsp;{{ preview.error }}
        </BAlert>
        <div v-else-if="preview.image">
          <img :src="`data:image/png;base64,${preview.image}`" alt="Website preview" class="preview-img" />
        </div>
      </div>

      <!-- Bulk analysis tool (textarea, up to 100 items) -->
      <div class="tool-block border rounded p-2 mb-0">
        <div class="d-flex align-items-center justify-content-between mb-1">
          <span class="bold"><i class="fas fa-list fa-fw"></i>&nbsp;Bulk analysis</span>
          <BButton
            variant="outline-secondary"
            size="sm"
            :disabled="bulkItems.length === 0 || bulkItems.length > MAX_BULK_ITEMS || bulk.loading"
            @click="runBulk"
          >
            <BSpinner v-if="bulk.loading" small></BSpinner>
            <i v-else class="fa fa-play"></i>&nbsp;Analyze
          </BButton>
        </div>

        <BFormTextarea
          v-model="bulkText"
          placeholder="One domain or IP per line (max 100)"
          rows="4"
          max-rows="10"
          size="sm"
        ></BFormTextarea>
        <div class="small mt-1" :class="bulkItems.length > MAX_BULK_ITEMS ? 'text-danger' : 'text-muted'">
          {{ bulkItems.length }} / {{ MAX_BULK_ITEMS }} items
          <span v-if="bulkItems.length > MAX_BULK_ITEMS">
            &mdash; too many items, remove {{ bulkItems.length - MAX_BULK_ITEMS }}
          </span>
        </div>

        <div v-if="bulk.loading" class="text-muted small mt-2">
          <BSpinner small></BSpinner>&nbsp;&nbsp;Analyzing {{ bulkItems.length }} items...
        </div>
        <BAlert v-else-if="bulk.error" :model-value="true" variant="danger" class="py-1 px-2 mb-0 mt-2 small">
          <i class="fas fa-triangle-exclamation"></i>&nbsp;{{ bulk.error }}
        </BAlert>
        <div v-else-if="bulk.items.length" class="mt-2">
          <div v-for="(item, idx) in bulk.items" :key="idx" class="mb-2">
            <div class="bold small">
              <i class="fas fa-angle-right"></i>&nbsp;{{ item.target }}
              <span v-if="item.result && item.result.exitError" class="text-warning">
                &nbsp;<i class="fas fa-circle-exclamation"></i>&nbsp;error
              </span>
            </div>
            <pre class="tool-output mb-0">{{ item.result ? item.result.output : '' }}</pre>
          </div>
        </div>
      </div>
    </BCard>
  </div>
</template>

<script>
import { ref, reactive, computed, watch, nextTick } from 'vue'
import { useApi } from '../../composables/useApi'
import { NETWORK_TOOLS } from '../../composables/useNetworkTools'

const MAX_BULK_ITEMS = 100

// Icons for the core network tools (keyed by the single-source tool name).
const TOOL_ICONS = {
  rdap: 'fa-id-card',
  dig: 'fa-magnifying-glass',
  ping: 'fa-satellite-dish',
  traceroute: 'fa-route'
}

// Additional threat-hunting tools rendered as text-output tools.
const ADDITIONAL_TOOLS = [
  { name: 'reverse_dns', label: 'Reverse DNS (PTR)', icon: 'fa-arrows-rotate' },
  { name: 'nsmx', label: 'NS / MX records', icon: 'fa-envelope' },
  { name: 'geoip', label: 'GeoIP', icon: 'fa-globe' },
  { name: 'asn', label: 'ASN', icon: 'fa-network-wired' },
  { name: 'tls_cert', label: 'TLS certificate', icon: 'fa-lock' },
  { name: 'reputation', label: 'Reputation / threat intel', icon: 'fa-shield-halved' }
]

export default {
  name: 'ResearchTools',
  setup(props, { expose }) {
    const { post } = useApi()

    const target = ref('')
    const dnsServer = ref('')

    // Build the ordered list of text-output tools from the single-source
    // NETWORK_TOOLS definitions plus the additional threat-hunting tools.
    const textTools = NETWORK_TOOLS.map(t => ({
      name: t.name,
      label: t.label,
      icon: TOOL_ICONS[t.name] || 'fa-wrench'
    })).concat(ADDITIONAL_TOOLS)

    // Per-tool output state: { loading, error, output, truncated, exitError }.
    const results = reactive({})
    for (const t of textTools) {
      results[t.name] = { loading: false, error: null, output: null, truncated: false, exitError: false }
    }

    // Website-preview state.
    const preview = reactive({ loading: false, error: null, image: null })

    // Bulk-analysis state.
    const bulkText = ref('')
    const bulk = reactive({ loading: false, error: null, items: [] })

    const bulkItems = computed(() =>
      bulkText.value
        .split('\n')
        .map(line => line.trim())
        .filter(line => line.length > 0)
    )

    // Reset every target-driven result region (text tools + website preview) to
    // its initial empty state. Used both when the shared target changes and when
    // the tools view is (re)opened for a new target from the context menu.
    const resetTargetResults = () => {
      for (const t of textTools) {
        results[t.name] = { loading: false, error: null, output: null, truncated: false, exitError: false }
      }
      preview.loading = false
      preview.error = null
      preview.image = null
    }

    // When a new target is selected/typed, clear all previous results so stale
    // output from a prior target is never shown alongside the new one.
    watch(target, () => {
      resetTargetResults()
    })

    // Run a single text-output tool through the research_tool endpoint.
    const runTool = async (toolName) => {
      const state = results[toolName]
      if (!state || !target.value || state.loading) return

      state.loading = true
      state.error = null
      state.output = null
      state.truncated = false
      state.exitError = false

      const body = { tool: toolName, target: target.value }
      if (toolName === 'dig' && dnsServer.value) {
        body.dns_server = dnsServer.value
      }

      try {
        const resp = await post({ req: 'research_tool' }, body)
        if (resp && resp.status === 'ok') {
          const data = resp.data || {}
          state.output = data.output != null ? data.output : ''
          state.truncated = !!data.truncated
          state.exitError = !!data.exitError
        } else {
          state.error = (resp && resp.reason) || 'Tool execution failed'
        }
      } catch (e) {
        state.error = e && e.message ? e.message : 'Tool execution failed'
      } finally {
        state.loading = false
      }
    }

    // Run the website-preview tool and render the returned base64 PNG.
    const runPreview = async () => {
      if (!target.value || preview.loading) return
      preview.loading = true
      preview.error = null
      preview.image = null
      try {
        const resp = await post({ req: 'research_tool' }, { tool: 'website_preview', target: target.value })
        if (resp && resp.status === 'ok') {
          const data = resp.data || {}
          if (data.image) {
            preview.image = data.image
          } else {
            preview.error = data.reason || 'No preview available'
          }
        } else {
          preview.error = (resp && resp.reason) || 'No preview available'
        }
      } catch (e) {
        preview.error = e && e.message ? e.message : 'Preview capture failed'
      } finally {
        preview.loading = false
      }
    }

    // Run bulk analysis for up to MAX_BULK_ITEMS items (one per line).
    const runBulk = async () => {
      const items = bulkItems.value
      if (items.length === 0 || items.length > MAX_BULK_ITEMS || bulk.loading) return

      bulk.loading = true
      bulk.error = null
      bulk.items = []
      try {
        const resp = await post({ req: 'research_tool' }, { tool: 'bulk', target: '', items })
        if (resp && resp.status === 'ok') {
          bulk.items = (resp.data && resp.data.items) || []
        } else {
          bulk.error = (resp && resp.reason) || 'Bulk analysis failed'
        }
      } catch (e) {
        bulk.error = e && e.message ? e.message : 'Bulk analysis failed'
      } finally {
        bulk.loading = false
      }
    }

    // Programmatically drive the tools view: set the shared target and run a
    // single text-output tool. Used by the ToolsModal launched from a context
    // menu so the domain is prefilled and the selected tool runs immediately.
    const runWith = async (newTarget, toolName) => {
      target.value = String(newTarget || '')
      // Let the target watcher clear any stale results before we start the run.
      await nextTick()
      if (toolName && results[toolName]) {
        runTool(toolName)
      }
    }

    // Exposed so a parent (ToolsModal) can prefill + auto-run via a template ref.
    expose({ runWith })

    return {
      MAX_BULK_ITEMS,
      target,
      dnsServer,
      textTools,
      results,
      preview,
      bulkText,
      bulk,
      bulkItems,
      runTool,
      runPreview,
      runBulk,
      runWith
    }
  }
}
</script>

<style scoped>
.tool-block {
  background-color: rgba(0, 0, 0, 0.02);
}
.tool-output {
  white-space: pre-wrap;
  word-break: break-word;
  max-height: 320px;
  overflow: auto;
  font-size: 0.8rem;
  margin: 0;
  padding: 0.5rem;
  background-color: #1e1e1e;
  color: #e0e0e0;
  border-radius: 4px;
}
.preview-img {
  max-width: 100%;
  height: auto;
  border: 1px solid #ccc;
  border-radius: 4px;
}
</style>
