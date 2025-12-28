<template>
  <div>
    <b-card-group deck>
      <!-- CA Root Certificate Card -->
      <b-card header="CA root certificate" body-class="p-2">
        <p>
          CA root certificate is used to sign all SSL certificates. 
          Install certificate to your browser/OS to avoid displaying 
          certificate error message before the block page.
        </p>
        <a 
          :href="'/rpi_admin/rpidata.php?req=download&file=CA'" 
          class="btn btn-secondary btn-sm"
        >
          <i class="fa fa-download"></i>&nbsp;&nbsp;Download
        </a>
      </b-card>

      <!-- Database Card -->
      <b-card header="Database" body-class="p-2">
        <p>
          SQLite database stores all DNS query and RPZ logs, application settings. 
          If you need to keep the data - periodically backup the database.
        </p>
        <a 
          :href="'/rpi_admin/rpidata.php?req=download&file=DB'" 
          class="btn btn-secondary btn-sm"
        >
          <i class="fa fa-download"></i>&nbsp;&nbsp;Download
        </a>
        <b-button 
          v-b-tooltip.hover 
          title="Import" 
          variant="secondary" 
          size="sm" 
          @click.stop="openImportModal"
        >
          <i class="fa fa-upload"></i>&nbsp;&nbsp;Import
        </b-button>
      </b-card>

      <!-- ISC Bind Logs Card -->
      <b-card header="ISC Bind logs files" body-class="p-2">
        <p>
          Bind log files contain internal DNS server log messages, 
          raw DNS query log and RPZ log messages. 
          bind_queries.log contains DNS query and rpz logs.
        </p>
        <b-input-group>
          <a 
            :href="'/rpi_admin/rpidata.php?req=download&file=bind.log'" 
            class="btn btn-secondary btn-sm"
          >
            <i class="fa fa-download"></i>&nbsp;&nbsp;bind.log
          </a>&nbsp;&nbsp;&nbsp;
          <a 
            :href="'/rpi_admin/rpidata.php?req=download&file=bind_queries.log'" 
            class="btn btn-secondary btn-sm"
          >
            <i class="fa fa-download"></i>&nbsp;&nbsp;bind_queries.log
          </a>&nbsp;&nbsp;&nbsp;
          <a 
            :href="'/rpi_admin/rpidata.php?req=download&file=bind_rpz.log'" 
            class="btn btn-secondary btn-sm"
          >
            <i class="fa fa-download"></i>&nbsp;&nbsp;bind_rpz.log
          </a>
        </b-input-group>
      </b-card>
    </b-card-group>
  </div>
</template>

<script>
export default {
  name: 'Tools',
  methods: {
    openImportModal() {
      // Emit event to parent to open import modal with default selections
      this.$emit('open-import-modal', {
        db_import_type: [
          'assets', 'bl', 'wl', 
          'q_raw', 'h_raw', 
          'q_5m', 'h_5m', 
          'q_1h', 'h_1h', 
          'q_1d', 'h_1d'
        ]
      })
    }
  }
}
</script>

<style scoped>
/* Component-specific styles */
</style>
