import { defineConfig } from 'vite';
import path from 'path';

export default defineConfig({
  publicDir: false,
  build: {
    outDir: 'public/dist',
    emptyOutDir: false,
    lib: {
      entry: path.resolve('resources/codemirror/index.ts'),
      name: 'eCodeMirror',
      formats: ['iife'],
      fileName: () => 'eCodeMirror.js'
    },
    rollupOptions: {
      output: {
        assetFileNames: () => 'eCodeMirror.css'
      }
    }
  }
});
