import { defineConfig } from 'vitest/config'
import vue from '@vitejs/plugin-vue'
import path from 'path'

// Dedicated Vitest config so the Vue SFC plugin is applied when tests import
// .vue single-file components. The application build config remains in
// vite.config.js.
export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, 'src')
    }
  },
  test: {
    environment: 'jsdom',
    globals: true
  }
})
