<template>
  <div>
    <div class="v-spacer"></div>
    <b-card no-body>
      <b-tabs card v-model="activeTab">
        <!-- Assets Tab -->
        <b-tab title="Assets" active>
          <Assets 
            :logs_height="logs_height"
            @navigate="$emit('navigate', $event)"
            @add-asset="$emit('add-asset', $event)"
            @delete-asset="$emit('delete-asset', $event)"
          />
        </b-tab>

        <!-- RPZ Feeds Tab -->
        <b-tab title="RPZ Feeds" lazy>
          <RpzFeeds :logs_height="logs_height" />
        </b-tab>

        <!-- Block Tab -->
        <b-tab title="Block" lazy>
          <BlockList 
            :logs_height="logs_height"
            @navigate="$emit('navigate', $event)"
            @add-ioc="$emit('add-ioc', $event)"
            @delete-ioc="$emit('delete-ioc', $event)"
            @show-info="$emit('show-info', $event)"
          />
        </b-tab>

        <!-- Allow Tab -->
        <b-tab title="Allow" lazy>
          <AllowList 
            :logs_height="logs_height"
            @navigate="$emit('navigate', $event)"
            @add-ioc="$emit('add-ioc', $event)"
            @delete-ioc="$emit('delete-ioc', $event)"
            @show-info="$emit('show-info', $event)"
          />
        </b-tab>

        <!-- Settings Tab -->
        <b-tab title="Settings" lazy>
          <Settings 
            @show-info="$emit('show-info', $event)"
          />
        </b-tab>

        <!-- Tools Tab -->
        <b-tab title="Tools" lazy>
          <Tools 
            @open-import-modal="$emit('open-import-modal', $event)"
          />
        </b-tab>
      </b-tabs>
    </b-card>
  </div>
</template>

<script>
import Assets from './Assets.vue'
import RpzFeeds from './RpzFeeds.vue'
import BlockList from './BlockList.vue'
import AllowList from './AllowList.vue'
import Settings from './Settings.vue'
import Tools from './Tools.vue'

export default {
  name: 'AdminTabs',
  components: {
    Assets,
    RpzFeeds,
    BlockList,
    AllowList,
    Settings,
    Tools
  },
  props: {
    logs_height: {
      type: Number,
      default: 150
    }
  },
  data() {
    return {
      activeTab: 0
    }
  }
}
</script>

<style scoped>
.v-spacer {
  height: 10px;
}
</style>
