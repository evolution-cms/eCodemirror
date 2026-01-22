import { defineConfig } from 'vite';
import path from 'path';

export default defineConfig({
  publicDir: false,
  build: {
    outDir: 'public/dist',
    emptyOutDir: true,
    manifest: 'manifest.json',
    lib: {
      entry: path.resolve('resources/codemirror/index.ts'),
      name: 'eCodeMirror',
      formats: ['iife'],
      fileName: () => 'eCodeMirror.[hash].js'
    },
    rollupOptions: {
      output: {
        entryFileNames: 'eCodeMirror.[hash].js',
        chunkFileNames: 'eCodeMirror.[hash].js',
        assetFileNames: ({ name }) => {
          if (name && name.endsWith('.css')) {
            return 'eCodeMirror.[hash].css';
          }
          return 'assets/[name]-[hash][extname]';
        }
      }
    }
  }
});
