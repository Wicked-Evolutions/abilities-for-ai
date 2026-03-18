import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
  plugins: [vue()],
  base: '/wp-content/plugins/abilities-for-ai/admin/kl/',
  build: {
    outDir: '../admin/kl',
    emptyOutDir: true,
    chunkSizeWarningLimit: 600,
    rollupOptions: {
      input: 'src/main.js',
      output: {
        entryFileNames: 'js/app.js',
        chunkFileNames: 'js/[name].js',
        assetFileNames: (assetInfo) => {
          if (assetInfo.names?.[0]?.endsWith('.css')) {
            return 'css/[name][extname]'
          }
          return 'assets/[name][extname]'
        },
        manualChunks: {
          vendor: ['vue', 'vue-router', 'pinia', 'element-plus'],
        },
      },
    },
  },
})
