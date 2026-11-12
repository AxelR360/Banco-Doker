import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

export default defineConfig({
  plugins: [react()],
  build: {
    outDir: 'dist',
    emptyOutDir: true,
    // Asegurar que el rollup no ignore el entry correcto
    rollupOptions: {
      input: 'index.html',
    }
  },
  server: {
    port: 5173,
    proxy: {
      // En desarrollo local, redirige las llamadas a la API al backend PHP
      '/api': {
        target: 'http://localhost:80',
        changeOrigin: true,
      }
    }
  }
})
