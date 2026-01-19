<template>
  <div>
    <div class="v-spacer"></div>
    <BCard no-body>
      <BTabs card v-model="activeTab" @update:model-value="onTabChange">
        <!-- Assets Tab -->
        <BTab title="Assets" active>
          <Assets 
            ref="assetsRef"
            :logs_height="logs_height"
            @navigate="$emit('navigate', $event)"
            @add-asset="$emit('add-asset', $event)"
            @delete-asset="$emit('delete-asset', $event)"
          />
        </BTab>

        <!-- RPZ Feeds Tab -->
        <BTab title="RPZ Feeds" lazy>
          <RpzFeeds ref="rpzFeedsRef" :logs_height="logs_height" @show-info="$emit('show-info', $event)" />
        </BTab>

        <!-- Block Tab -->
        <BTab title="Block" lazy>
          <BlockList 
            ref="blockListRef"
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
            ref="allowListRef"
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

        <!-- Users Tab (Admin only) -->
        <BTab v-if="isAdmin" title="Users" lazy>
          <UserManager ref="userManagerRef" @show-info="$emit('show-info', $event)" />
        </BTab>
      </BTabs>
    </BCard>
  </div>
</template>

<script>
import { ref, inject, nextTick } from 'vue'
import Assets from './Assets.vue'
import RpzFeeds from './RpzFeeds.vue'
import BlockList from './BlockList.vue'
import AllowList from './AllowList.vue'
import Settings from './Settings.vue'
import Tools from './Tools.vue'
import UserManager from './UserManager.vue'

export default {
  name: 'AdminTabs',
  components: { Assets, RpzFeeds, BlockList, AllowList, Settings, Tools, UserManager },
  props: {
    logs_height: { type: Number, default: 150 }
  },
  emits: ['navigate', 'add-asset', 'delete-asset', 'add-ioc', 'delete-ioc', 'show-info', 'open-import-modal'],
  setup() {
    const activeTab = ref(0)
    const isAdmin = inject('isAdmin', ref(false))
    
    const assetsRef = ref(null)
    const rpzFeedsRef = ref(null)
    const blockListRef = ref(null)
    const allowListRef = ref(null)
    const userManagerRef = ref(null)

    const onTabChange = (tabIndex) => {
      nextTick(() => {
        switch (tabIndex) {
          case 0: assetsRef.value?.refreshTable?.(); break
          case 1: rpzFeedsRef.value?.fetchData?.(); break
          case 2: blockListRef.value?.refreshTable?.(); break
          case 3: allowListRef.value?.refreshTable?.(); break
          case 6: userManagerRef.value?.fetchUsers?.(); break
        }
      })
    }

    return { 
      activeTab, isAdmin, 
      assetsRef, rpzFeedsRef, blockListRef, allowListRef, userManagerRef,
      onTabChange 
    }
  }
}
</script>
