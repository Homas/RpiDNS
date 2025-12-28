import { defineConfig } from 'vite'
import vue2 from '@vitejs/plugin-vue2'
import path from 'path'

export default defineConfig({
  plugins: [vue2()],
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
          'vendor-bootstrap': ['bootstrap-vue', 'bootstrap/dist/css/bootstrap.css'],
          'vendor-charts': ['apexcharts', 'vue-apexcharts'],
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
