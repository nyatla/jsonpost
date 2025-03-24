// vite.config.ts

import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
  base: './',   // ← これで相対パスで出力されるようになります
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
  build: {
    sourcemap: false,// eval を使う余地を排除    
    rollupOptions: {
      output: {
        assetFileNames: (assetInfo) => {
          if (assetInfo.name === 'style.css') {
            return 'assets/style.css'; // 固定ファイル名で出力
          }
          return 'assets/[name][extname]'; // その他はハッシュ無しで
        },
      },
    },
  },
});
