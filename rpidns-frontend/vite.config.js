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
        manualChunks: {
          'vendor-vue': ['vue'],
          'vendor-bootstrap': ['bootstrap-vue-next', 'bootstrap/dist/css/bootstrap.css'],
          'vendor-charts': ['apexcharts', 'vue3-apexcharts'],
          'vendor-utils': ['axios']
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
