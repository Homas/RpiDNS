<!-- (c) Vadim Pavlov 2020 - 2026 -->
<!--
  Research.vue
  Container for the Research page. Bundles the three research sections
  (Unique queries, SQL query tool, and network research tools) behind the
  existing session-based authentication.

  It receives the same period/custom-period props and emits the same events as
  the other page components (QueryLog, RpzHits) so App.vue can wire it
  identically:
    Props:  isActive, logs_height, customStart, customEnd (+ period)
    Events: add-ioc, custom-period-change, show-info

  Child events are re-emitted verbatim to the parent so the container stays a
  thin pass-through (Req 1.3).
-->
<template>
  <div class="h-100 overflow-auto">
    <!-- Unique queries view -->
    <UniqueQueriesView
      :filter="filter"
      :period="period"
      :logs_height="logs_height"
      :is-active="isActive"
      :custom-start="customStart"
      :custom-end="customEnd"
      @add-ioc="onAddIoc"
      @custom-period-change="onCustomPeriodChange"
      @show-info="onShowInfo"
    />

    <!-- SQL query tool -->
    <SqlQueryTool @show-info="onShowInfo" />

    <!-- Network research tools -->
    <div class="p-2">
      <ResearchTools />
    </div>
  </div>
</template>

<script>
import UniqueQueriesView from './UniqueQueriesView.vue'
import SqlQueryTool from './SqlQueryTool.vue'
import ResearchTools from './ResearchTools.vue'

export default {
  name: 'Research',
  components: { UniqueQueriesView, SqlQueryTool, ResearchTools },
  props: {
    isActive: { type: Boolean, default: false },
    logs_height: { type: Number, default: 150 },
    customStart: { type: Number, default: null },
    customEnd: { type: Number, default: null },
    period: { type: String, default: '30m' },
    filter: { type: String, default: '' }
  },
  emits: ['add-ioc', 'custom-period-change', 'show-info'],
  setup(props, { emit }) {
    // Bubble the block/allow request up to App.vue (Req 1.3)
    const onAddIoc = (payload) => emit('add-ioc', payload)

    // Propagate custom period selection so App.vue can persist it across tabs
    const onCustomPeriodChange = (payload) => emit('custom-period-change', payload)

    // Forward toast notifications using the positional (msg, time) signature
    // that App.vue's showInfo handler and the other page components use.
    const onShowInfo = (msg, time) => emit('show-info', msg, time)

    return { onAddIoc, onCustomPeriodChange, onShowInfo }
  }
}
</script>
