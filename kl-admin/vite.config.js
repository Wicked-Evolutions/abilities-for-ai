import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
  plugins: [vue()],
  base: '/wp-content/plugins/abilities-for-ai/admin/kl/',
  build: {
    outDir: '../admin/kl',
    emptyOutDir: true,
    chunkSizeWarningLimit: 1500,
    cssCodeSplit: false,
    rollupOptions: {
      input: 'src/main.js',
      output: {
        format: 'iife',
        entryFileNames: 'js/app.js',
        assetFileNames: (assetInfo) => {
          if (assetInfo.names?.[0]?.endsWith('.css')) {
            return 'css/[name][extname]'
          }
          return 'assets/[name][extname]'
        },
        inlineDynamicImports: true,
      },
    },
  },
})
