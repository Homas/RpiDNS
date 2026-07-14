<!-- (c) Vadim Pavlov 2020 - 2026 -->
<template>
  <div
    v-if="visible"
    ref="menuRef"
    class="context-menu"
    :style="menuStyle"
    role="menu"
    aria-label="Context menu"
  >
    <!-- Domain Header -->
    <div class="context-menu-header text-truncate" :title="domain">
      {{ domain }}
    </div>

    <div v-if="showResearch" class="context-menu-divider"></div>

    <!-- Research Section -->
    <div v-if="showResearch" class="context-menu-section-label">
      <i class="fas fa-search fa-sm"></i>&nbsp;Research
    </div>
    <a
      v-if="showResearch"
      v-for="link in researchUrls"
      :key="link.name"
      :href="link.url"
      target="_blank"
      rel="noopener noreferrer"
      class="context-menu-item context-menu-link"
      role="menuitem"
    >
      {{ link.name }}
    </a>

    <!-- Tools Section (network research tools from the single-source useNetworkTools) -->
    <template v-if="showToolsGroup">
      <div class="context-menu-divider"></div>
      <div class="context-menu-section-label">
        <i class="fas fa-toolbox fa-sm"></i>&nbsp;Tools
      </div>
      <button
        v-for="tool in toolActions"
        :key="tool.name"
        class="context-menu-item context-menu-action"
        role="menuitem"
        :disabled="toolState.running"
        @click="onToolClick(tool)"
      >
        <i
          v-if="toolState.running && toolState.toolName === tool.name"
          class="fas fa-spinner fa-spin fa-sm"
        ></i>
        <i v-else class="fas fa-wrench fa-sm"></i>
        &nbsp;{{ tool.label }}
      </button>

      <!-- Tool loading / output / error region -->
      <div
        v-if="toolState.running || toolState.output !== null || toolState.error !== null"
        class="context-menu-tool-result"
      >
        <div v-if="toolState.running" class="context-menu-tool-loading">
          <i class="fas fa-spinner fa-spin fa-sm"></i>&nbsp;Running {{ toolState.toolName }}&hellip;
        </div>
        <div v-else-if="toolState.error !== null" class="context-menu-tool-error">
          <i class="fas fa-exclamation-triangle fa-sm"></i>&nbsp;{{ toolState.error }}
        </div>
        <pre v-else-if="toolState.output !== null" class="context-menu-tool-output">{{ toolState.output }}</pre>
      </div>
    </template>

    <div class="context-menu-divider"></div>

    <!-- Actions Section -->
    <div class="context-menu-section-label">
      <i class="fas fa-mouse-pointer fa-sm"></i>&nbsp;Actions
    </div>
    <button
      v-for="action in actions"
      :key="action.label"
      class="context-menu-item context-menu-action"
      role="menuitem"
      @click="onActionClick(action)"
    >
      <i v-if="action.icon" :class="action.icon"></i>
      <span v-if="action.icon">&nbsp;</span>
      {{ action.label }}
    </button>
  </div>
</template>

<script>
import { ref, computed, watch, nextTick, onBeforeUnmount } from 'vue'
import { getResearchUrls } from '../composables/useResearchLinks.js'
import { getToolActions } from '../composables/useNetworkTools.js'
import { useApi } from '../composables/useApi.js'

export default {
  name: 'ContextMenu',
  props: {
    visible: {
      type: Boolean,
      default: false
    },
    domain: {
      type: String,
      default: ''
    },
    x: {
      type: Number,
      default: 0
    },
    y: {
      type: Number,
      default: 0
    },
    actions: {
      type: Array,
      default: () => [],
      validator: (value) => value.every(a => typeof a.label === 'string')
    },
    showResearch: {
      type: Boolean,
      default: true
    },
    showTools: {
      type: Boolean,
      default: true
    }
  },
  emits: ['update:visible', 'action'],
  setup(props, { emit }) {
    const api = useApi()
    const menuRef = ref(null)
    const adjustedX = ref(0)
    const adjustedY = ref(0)

    const researchUrls = computed(() => {
      return props.domain ? getResearchUrls(props.domain) : []
    })

    // Network tool actions come from the single-source useNetworkTools composable,
    // so adding/removing a tool there propagates to every page using the ContextMenu.
    const toolActions = computed(() => {
      return props.domain ? getToolActions(props.domain) : []
    })

    // Tools are rendered as a separate labeled group between the Research links and
    // the page-specific Actions. They are shown on the same (domain) menus as the
    // research links; column-filter menus pass show-research=false and thus hide them.
    const showToolsGroup = computed(() => {
      return props.showTools && props.showResearch && toolActions.value.length > 0
    })

    // Loading / output / error state for a tool launched from the context menu.
    const toolState = ref({
      running: false,
      toolName: null,
      output: null,
      error: null
    })

    function resetToolState() {
      toolState.value = { running: false, toolName: null, output: null, error: null }
    }

    // Execute the selected network tool via the research_tool endpoint, showing a
    // loading indication while it runs and an error indication on failure. Running a
    // tool never mutates the originating log row (no add-ioc/refresh is emitted).
    const onToolClick = async (tool) => {
      if (toolState.value.running) return
      toolState.value = { running: true, toolName: tool.name, output: null, error: null }
      try {
        const res = await api.post(
          { req: 'research_tool' },
          { tool: tool.name, target: tool.domain }
        )
        if (res && res.status === 'ok') {
          const output = res.data && res.data.output != null ? String(res.data.output) : ''
          toolState.value = { running: false, toolName: tool.name, output, error: null }
        } else {
          const reason = (res && res.reason) ? res.reason : 'Tool execution failed'
          toolState.value = { running: false, toolName: tool.name, output: null, error: reason }
        }
      } catch (err) {
        const reason = (err && err.message) ? err.message : 'Tool execution failed'
        toolState.value = { running: false, toolName: tool.name, output: null, error: reason }
      }
    }

    const menuStyle = computed(() => ({
      position: 'fixed',
      left: `${adjustedX.value}px`,
      top: `${adjustedY.value}px`,
      zIndex: 1050
    }))

    // --- Viewport clamping ---
    function clampToViewport() {
      const el = menuRef.value
      if (!el) return

      const rect = el.getBoundingClientRect()
      const viewportWidth = window.innerWidth
      const viewportHeight = window.innerHeight

      let newX = props.x
      let newY = props.y

      // If menu overflows right edge, shift left
      if (newX + rect.width > viewportWidth) {
        newX = newX - (rect.width - (viewportWidth - newX))
      }
      // If menu overflows bottom edge, shift up
      if (newY + rect.height > viewportHeight) {
        newY = newY - (rect.height - (viewportHeight - newY))
      }

      // Ensure we don't go negative
      adjustedX.value = Math.max(0, newX)
      adjustedY.value = Math.max(0, newY)
    }

    // --- Dismissal handlers ---
    function onClickOutside(event) {
      const el = menuRef.value
      if (el && !el.contains(event.target)) {
        emit('update:visible', false)
      }
    }

    function onEscapeKey(event) {
      if (event.key === 'Escape') {
        emit('update:visible', false)
      }
    }

    function addListeners() {
      document.addEventListener('mousedown', onClickOutside)
      document.addEventListener('keydown', onEscapeKey)
    }

    function removeListeners() {
      document.removeEventListener('mousedown', onClickOutside)
      document.removeEventListener('keydown', onEscapeKey)
    }

    // --- Watch visibility to manage listeners and clamping ---
    watch(() => props.visible, (isVisible) => {
      if (isVisible) {
        // Clear any prior tool output/error when the menu is (re)opened
        resetToolState()
        // Set initial position from props before clamping
        adjustedX.value = props.x
        adjustedY.value = props.y
        // Clamp after DOM renders
        nextTick(() => {
          clampToViewport()
        })
        addListeners()
      } else {
        removeListeners()
      }
    })

    // Reset tool state when the menu targets a different domain
    watch(() => props.domain, () => {
      resetToolState()
    })

    // Also re-clamp when x or y change while visible (e.g., opening on a new target)
    watch([() => props.x, () => props.y], () => {
      if (props.visible) {
        adjustedX.value = props.x
        adjustedY.value = props.y
        nextTick(() => {
          clampToViewport()
        })
      }
    })

    // Cleanup on unmount to avoid leaks
    onBeforeUnmount(() => {
      removeListeners()
    })

    const onActionClick = (action) => {
      emit('action', { actionName: action.label, domain: props.domain })
      if (typeof action.handler === 'function') {
        action.handler()
      }
      emit('update:visible', false)
    }

    return {
      menuRef,
      researchUrls,
      toolActions,
      showToolsGroup,
      toolState,
      onToolClick,
      menuStyle,
      adjustedX,
      adjustedY,
      onActionClick
    }
  }
}
</script>

<style scoped>
.context-menu {
  background-color: #2b2730;
  border: 1px solid #444;
  border-radius: 6px;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.4);
  min-width: 200px;
  max-width: 280px;
  padding: 4px 0;
  font-size: 0.875rem;
  color: #e0e0e0;
  user-select: none;
}

.context-menu-header {
  padding: 6px 12px;
  font-weight: 600;
  color: #fff;
  font-size: 0.85rem;
  max-width: 280px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.context-menu-section-label {
  padding: 4px 12px;
  font-size: 0.75rem;
  color: #aaa;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.context-menu-divider {
  height: 1px;
  background-color: #444;
  margin: 4px 0;
}

.context-menu-item {
  display: block;
  width: 100%;
  padding: 4px 12px 4px 20px;
  border: none;
  background: none;
  color: #e0e0e0;
  text-align: left;
  text-decoration: none;
  cursor: pointer;
  font-size: 0.85rem;
  line-height: 1.5;
}

.context-menu-item:hover {
  background-color: #3a3540;
  color: #fff;
  text-decoration: none;
}

.context-menu-link:visited {
  color: #e0e0e0;
}

.context-menu-link:hover {
  color: #fff;
}

.context-menu-action {
  font-weight: 500;
}

.context-menu-item:disabled {
  opacity: 0.6;
  cursor: default;
}

.context-menu-tool-result {
  padding: 4px 12px 6px 12px;
  max-width: 280px;
}

.context-menu-tool-loading {
  color: #aaa;
  font-size: 0.8rem;
}

.context-menu-tool-error {
  color: #e6a1a1;
  font-size: 0.8rem;
  white-space: normal;
  word-break: break-word;
}

.context-menu-tool-output {
  margin: 0;
  padding: 6px 8px;
  background-color: #201d24;
  border: 1px solid #444;
  border-radius: 4px;
  color: #d0d0d0;
  font-size: 0.75rem;
  line-height: 1.35;
  max-height: 220px;
  max-width: 256px;
  overflow: auto;
  white-space: pre-wrap;
  word-break: break-word;
}
</style>
