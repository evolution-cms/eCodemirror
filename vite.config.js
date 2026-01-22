import { defineConfig } from 'vite';
import path from 'path';

export default defineConfig({
  publicDir: false,
  build: {
    outDir: 'public/dist',
    emptyOutDir: true,
    lib: {
      entry: path.resolve('resources/codemirror/index.ts'),
      name: 'eCodeMirror',
      formats: ['iife'],
      fileName: () => 'eCodeMirror.js'
    },
    rollupOptions: {
      output: {
        entryFileNames: 'eCodeMirror.js',
        chunkFileNames: 'assets/[name].js',
        assetFileNames: ({ name }) => {
          if (name && name.endsWith('.css')) {
            return 'eCodeMirror.css';
          }
          return 'assets/[name][extname]';
        }
      }
    }
  }
});
