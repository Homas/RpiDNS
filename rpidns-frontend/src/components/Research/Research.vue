<!-- (c) Vadim Pavlov 2020 - 2026 -->
<!--
  Research.vue
  Container for the Research page. Presents the three research sections as
  horizontal card sub-tabs (mirroring the Admin page pattern):
    - Unique Queries  (#i2r/3/unique)
    - SQL Query       (#i2r/3/sql)
    - Tools           (#i2r/3/tools)

  The active sub-tab is reflected in the URL hash as #i2r/3/{slug} so a
  specific tool is shareable/bookmarkable (deep-linking). It receives the same
  period/custom-period props and emits the same events as the other page
  components (QueryLog, RpzHits) so App.vue can wire it identically, and
  re-emits child events verbatim to the parent.
-->
<template>
  <div class="h-100 overflow-auto">
    <div class="v-spacer"></div>
    <BCard no-body>
      <BTabs card v-model="activeTab" @update:model-value="onSubTabChange">
        <!-- Unique Queries -->
        <BTab title="Unique Queries" lazy>
          <UniqueQueriesView
            :filter="filter"
            :period="period"
            :logs_height="logs_height"
            :is-active="isActive && activeTab === 0"
            :custom-start="customStart"
            :custom-end="customEnd"
            @add-ioc="onAddIoc"
            @custom-period-change="onCustomPeriodChange"
            @show-info="onShowInfo"
          />
        </BTab>

        <!-- SQL Query -->
        <BTab title="SQL Query" lazy>
          <SqlQueryTool @show-info="onShowInfo" />
        </BTab>

        <!-- Network Tools -->
        <BTab title="Tools" lazy>
          <ResearchTools />
        </BTab>
      </BTabs>
    </BCard>
  </div>
</template>

<script>
import { ref, onMounted } from 'vue'
import UniqueQueriesView from './UniqueQueriesView.vue'
import SqlQueryTool from './SqlQueryTool.vue'
import ResearchTools from './ResearchTools.vue'

// The Research tab occupies index 3 in App.vue's #i2r/{index} scheme.
const RESEARCH_TAB_INDEX = '3'
// Sub-tab slugs, indexed to match the BTab order below.
const SUBTABS = ['unique', 'sql', 'tools']

// Parse the current location hash into its slash/hash-delimited segments.
function hashParts() {
  return window.location.hash.split(/#|\//).filter(Boolean)
}

// Resolve the active sub-tab index from the current hash (defaults to 0).
function readSubTabFromHash() {
  const parts = hashParts()
  if (parts[0] === 'i2r' && parts[1] === RESEARCH_TAB_INDEX) {
    const idx = SUBTABS.indexOf(parts[2])
    if (idx >= 0) return idx
  }
  return 0
}

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
    // Initialize the active sub-tab from the hash so a direct link like
    // #i2r/3/sql opens the SQL tool immediately.
    const activeTab = ref(readSubTabFromHash())

    // Write the active sub-tab into the hash as #i2r/3/{slug}, preserving the
    // optional trailing `hidemenu` flag if it was present.
    const writeHash = (idx, replace) => {
      const parts = hashParts()
      const hadHidemenu = parts.includes('hidemenu')
      let h = '#i2r/' + RESEARCH_TAB_INDEX + '/' + SUBTABS[idx]
      if (hadHidemenu) h += '/hidemenu'
      if (replace) {
        history.replaceState(null, null, h)
      } else {
        history.pushState(null, null, h)
      }
    }

    // User switched sub-tabs: push a new hash entry so the back button works.
    const onSubTabChange = (idx) => {
      activeTab.value = idx
      writeHash(idx, false)
    }

    // Bubble child events up to App.vue unchanged.
    const onAddIoc = (payload) => emit('add-ioc', payload)
    const onCustomPeriodChange = (payload) => emit('custom-period-change', payload)
    const onShowInfo = (msg, time) => emit('show-info', msg, time)

    onMounted(() => {
      // Normalize the hash on entry (e.g. #i2r/3 -> #i2r/3/unique) without
      // adding a history entry.
      writeHash(activeTab.value, true)
    })

    return { activeTab, onSubTabChange, onAddIoc, onCustomPeriodChange, onShowInfo }
  }
}
</script>
