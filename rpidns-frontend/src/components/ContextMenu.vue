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
    }
  },
  emits: ['update:visible', 'action'],
  setup(props, { emit }) {
    const menuRef = ref(null)
    const adjustedX = ref(0)
    const adjustedY = ref(0)

    const researchUrls = computed(() => {
      return props.domain ? getResearchUrls(props.domain) : []
    })

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
</style>
