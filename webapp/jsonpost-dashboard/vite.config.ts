// vite.config.js

import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
  plugins: [vue()],
  server: {
    port: 7000,
    open: true, // 起動時にブラウザを自動で開く
    watch: {
      usePolling: true, // ファイル変更検出をポーリングに切り替え
    },  
  },
  css: {
    preprocessorOptions: {
      less: {
        javascriptEnabled: true,
      },
    },
  },
});