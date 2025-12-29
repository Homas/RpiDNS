<template>
  <div>
    <div class="row row-cols-1 row-cols-md-3 g-3">
      <!-- CA Root Certificate Card -->
      <div class="col">
        <BCard class="h-100">
          <template #header>CA root certificate</template>
          <p>
            CA root certificate is used to sign all SSL certificates. Install certificate to your browser/OS to remediate certificate error message on the block page.
          </p>
          <a :href="'/rpi_admin/rpidata.php?req=download&file=CA'" class="btn btn-secondary btn-sm">
            <i class="fa fa-download"></i>&nbsp;Download
          </a>
        </BCard>
      </div>

      <!-- Database Card -->
      <div class="col">
        <BCard class="h-100">
          <template #header>Database</template>
          <p>
            SQLite database stores all DNS query and RPZ logs, application settings. 
            You can manually backup the database.
          </p>
          <a :href="'/rpi_admin/rpidata.php?req=download&file=DB'" class="btn btn-secondary btn-sm">
            <i class="fa fa-download"></i>&nbsp;Download
          </a>&nbsp;
          <BButton v-b-tooltip.hover title="Import" variant="secondary" size="sm" @click.stop="openImportModal">
            <i class="fa fa-upload"></i>&nbsp;Import
          </BButton>
        </BCard>
      </div>

      <!-- ISC Bind Logs Card -->
      <div class="col">
        <BCard class="h-100">
          <template #header>ISC Bind logs files</template>
          <p>
            Bind log files contain internal DNS server log messages, 
            raw DNS query log and RPZ log messages. 
            bind_queries.log contains DNS query and rpz logs.
          </p>
          <a :href="'/rpi_admin/rpidata.php?req=download&file=bind.log'" class="btn btn-secondary btn-sm">
            <i class="fa fa-download"></i>&nbsp;bind.log
          </a>&nbsp;
          <a :href="'/rpi_admin/rpidata.php?req=download&file=bind_queries.log'" class="btn btn-secondary btn-sm">
            <i class="fa fa-download"></i>&nbsp;bind_queries.log
          </a>&nbsp;
          <a :href="'/rpi_admin/rpidata.php?req=download&file=bind_rpz.log'" class="btn btn-secondary btn-sm">
            <i class="fa fa-download"></i>&nbsp;bind_rpz.log
          </a>
        </BCard>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'Tools',
  emits: ['open-import-modal'],
  setup(props, { emit }) {
    const openImportModal = () => {
      emit('open-import-modal', {
        db_import_type: [
          'assets', 'bl', 'wl', 
          'q_raw', 'h_raw', 
          'q_5m', 'h_5m', 
          'q_1h', 'h_1h', 
          'q_1d', 'h_1d'
        ]
      })
    }

    return { openImportModal }
  }
}
</script>
