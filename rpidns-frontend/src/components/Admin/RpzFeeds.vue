<template>
  <div>
    <BRow>
      <BCol cols="12" lg="12">
        <BTableSimple id="rpz_feeds" :sticky-header="`${logs_height}px`" striped hover small>
          <BThead>
            <BTr>
              <BTh class="d-none d-sm-table-cell">Feed</BTh>
              <BTh>Feed action</BTh>
              <BTh class="d-none d-md-table-cell">Description</BTh>
              <BTh>Actions</BTh>
            </BTr>
          </BThead>
          <BTbody>
            <BTr v-for="item in tableItems" :key="item.feed">
              <BTd class="mw150 d-none d-sm-table-cell">{{ item.feed }}</BTd>
              <BTd class="mw150">{{ item.action }}</BTd>
              <BTd class="mw400 d-none d-md-table-cell">{{ item.desc }}</BTd>
              <BTd class="mw050">
                <BButton v-b-tooltip.hover title="Retransfer" variant="outline-secondary" size="sm" @click.stop="retransferRPZ(item)">
                  <i class="fas fa-redo"></i>
                </BButton>
              </BTd>
            </BTr>
          </BTbody>
        </BTableSimple>
        <div v-if="isLoading" class="text-center m-0 p-0">
          <BSpinner class="align-middle" small></BSpinner>&nbsp;&nbsp;<strong>Loading...</strong>
        </div>
      </BCol>
    </BRow>
  </div>
</template>

<script>
import { ref, onMounted } from 'vue'
import { useApi } from '@/composables/useApi'

export default {
  name: 'RpzFeeds',
  props: { logs_height: { type: Number, default: 150 } },
  emits: ['show-info'],
  setup(props, { emit }) {
    const api = useApi()
    const tableItems = ref([])
    const isLoading = ref(false)

    const fetchData = async () => {
      isLoading.value = true
      try {
        const response = await api.get({ req: 'rpz_feeds', sortBy: 'feed', sortDesc: false })
        tableItems.value = response.data || []
      } catch (error) {
        console.error('Error fetching RPZ feeds:', error)
        tableItems.value = []
      } finally {
        isLoading.value = false
      }
    }

    const retransferRPZ = async (item) => {
      try {
        const response = await api.put({ req: 'retransfer_feed' }, { feed: item.feed })
        if (response.status !== 'success') {
          emit('show-info', { msg: response.reason, time: 3 })
        } else {
          emit('show-info', { msg: 'Retransfer requested', time: 3 })
        }
      } catch (error) {
        console.error('Error requesting retransfer:', error)
        emit('show-info', { msg: 'Unknown error!!!', time: 3 })
      }
    }

    onMounted(() => { fetchData() })

    return { tableItems, isLoading, retransferRPZ }
  }
}
</script>

<style scoped>
.mw050 { max-width: 50px; }
.mw150 { max-width: 150px; }
.mw400 { max-width: 400px; }
</style>
