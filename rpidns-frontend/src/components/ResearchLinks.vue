<!-- (c) Vadim Pavlov 2020 - 2026 -->
<template>
  <span>
    <template v-for="(link, index) in researchUrls" :key="link.name">
      - <a target="_blank" :href="link.url">{{ link.name }}</a><br v-if="index < researchUrls.length - 1">
    </template>
  </span>
</template>

<script>
import { getResearchUrls } from '../composables/useResearchLinks.js'

export default {
  name: 'ResearchLinks',
  props: {
    domain: {
      type: String,
      required: true
    }
  },
  computed: {
    researchUrls() {
      return getResearchUrls(this.domain)
    }
  }
}
</script>
