<!-- (c) Vadim Pavlov 2020 - 2026 -->
<!--
  ToolsModal.vue
  App-level modal wrapper around the ResearchTools view. It listens for the
  global `open-research-tools` window event (dispatched by ContextMenu when
  Analyze is selected), opens with the right-clicked target prefilled, and runs
  every applicable tool (or a single named tool when one is supplied). Rendered
  once at the App root so every page that shows a context menu shares a single
  modal instance.
-->
<template>
  <BModal
    v-model="visible"
    :title="modalTitle"
    size="xl"
    scrollable
    hide-footer
    body-class="pt-0"
    @shown="onShown"
  >
    <ResearchTools ref="toolsRef" />
  </BModal>
</template>

<script>
import { ref, onMounted, onBeforeUnmount } from 'vue'
import ResearchTools from './ResearchTools.vue'

export default {
  name: 'ToolsModal',
  components: { ResearchTools },
  setup() {
    const visible = ref(false)
    const toolsRef = ref(null)
    const modalTitle = ref('Research tools')
    // The (target, tool) awaiting execution once the modal is fully shown.
    const pending = ref(null)

    // Open the modal for a given target/tool. The actual prefill + auto-run is
    // deferred to the modal's `shown` event (onShown) so the ResearchTools child
    // is guaranteed to be mounted and reachable via the template ref.
    const open = (detail) => {
      const target = detail && detail.target ? String(detail.target) : ''
      const tool = detail && detail.tool ? String(detail.tool) : ''
      modalTitle.value = target ? `Research tools \u2014 ${target}` : 'Research tools'
      pending.value = { target, tool }

      if (visible.value) {
        // Already open (e.g. a second right-click): run immediately since the
        // `shown` event will not fire again.
        flushPending()
      } else {
        visible.value = true
      }
    }

    const flushPending = () => {
      const job = pending.value
      pending.value = null
      if (job && toolsRef.value && typeof toolsRef.value.runWith === 'function') {
        toolsRef.value.runWith(job.target, job.tool)
      }
    }

    const onShown = () => {
      flushPending()
    }

    const onOpenEvent = (event) => {
      open(event && event.detail ? event.detail : {})
    }

    onMounted(() => {
      window.addEventListener('open-research-tools', onOpenEvent)
    })

    onBeforeUnmount(() => {
      window.removeEventListener('open-research-tools', onOpenEvent)
    })

    return {
      visible,
      toolsRef,
      modalTitle,
      onShown
    }
  }
}
</script>
