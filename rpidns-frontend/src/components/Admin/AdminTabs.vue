<template>
  <div class="h-100 overflow-auto p-2">
    <BCard no-body class="h-100">
      <BTabs card v-model="activeTab">
        <!-- Assets Tab -->
        <BTab title="Assets" active>
          <Assets 
            :logs_height="logs_height"
            @navigate="$emit('navigate', $event)"
            @add-asset="$emit('add-asset', $event)"
            @delete-asset="$emit('delete-asset', $event)"
          />
        </BTab>

        <!-- RPZ Feeds Tab -->
        <BTab title="RPZ Feeds" lazy>
          <RpzFeeds :logs_height="logs_height" @show-info="$emit('show-info', $event)" />
        </BTab>

        <!-- Block Tab -->
        <BTab title="Block" lazy>
          <BlockList 
            :logs_height="logs_height"
            @navigate="$emit('navigate', $event)"
            @add-ioc="$emit('add-ioc', $event)"
            @delete-ioc="$emit('delete-ioc', $event)"
            @show-info="$emit('show-info', $event)"
          />
        </BTab>

        <!-- Allow Tab -->
        <BTab title="Allow" lazy>
          <AllowList 
            :logs_height="logs_height"
            @navigate="$emit('navigate', $event)"
            @add-ioc="$emit('add-ioc', $event)"
            @delete-ioc="$emit('delete-ioc', $event)"
            @show-info="$emit('show-info', $event)"
          />
        </BTab>

        <!-- Settings Tab -->
        <BTab title="Settings" lazy>
          <Settings @show-info="$emit('show-info', $event)" />
        </BTab>

        <!-- Tools Tab -->
        <BTab title="Tools" lazy>
          <Tools @open-import-modal="$emit('open-import-modal', $event)" />
        </BTab>
      </BTabs>
    </BCard>
  </div>
</template>

<script>
import { ref } from 'vue'
import Assets from './Assets.vue'
import RpzFeeds from './RpzFeeds.vue'
import BlockList from './BlockList.vue'
import AllowList from './AllowList.vue'
import Settings from './Settings.vue'
import Tools from './Tools.vue'

export default {
  name: 'AdminTabs',
  components: { Assets, RpzFeeds, BlockList, AllowList, Settings, Tools },
  props: {
    logs_height: { type: Number, default: 150 }
  },
  emits: ['navigate', 'add-asset', 'delete-asset', 'add-ioc', 'delete-ioc', 'show-info', 'open-import-modal'],
  setup() {
    const activeTab = ref(0)
    return { activeTab }
  }
}
</script>
