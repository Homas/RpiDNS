import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import path from 'path'

export default defineConfig({
  plugins: [vue()],
  base: '/rpi_admin/dist/',
  build: {
    outDir: 'dist',
    assetsDir: 'assets',
    manifest: true,
    rollupOptions: {
      input: {
        main: path.resolve(__dirname, 'index.html')
      },
      output: {
        manualChunks(id) {
          if (id.includes('node_modules')) {
            if (id.includes('/vue/') || id.includes('/@vue/')) return 'vendor-vue'
            if (id.includes('bootstrap-vue-next') || id.includes('/bootstrap/')) return 'vendor-bootstrap'
            if (id.includes('apexcharts')) return 'vendor-charts'
            if (id.includes('axios')) return 'vendor-utils'
          }
        }
      }
    }
  },
  resolve: {
    alias: {
      '@': path.resolve(__dirname, 'src')
    }
  },
  server: {
    proxy: {
      '/rpi_admin/rpidata.php': {
        target: 'http://localhost:8080',
        changeOrigin: true
      }
    }
  }
})
