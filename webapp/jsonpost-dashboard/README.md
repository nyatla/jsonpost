# JsonPost ダッシュボード

このリポジトリは、JsonPost サーバ用の Web ダッシュボードです。ブラウザ上からサーバーの状態確認やデータ検索、統計取得、クライアントツールのダウンロードが行えます。

## セットアップ手順

### 1. 必要環境
- Node.js (推奨バージョン: 18.x 以降)
- npm (Node.js インストール時に同梱されています)

### 2. パッケージのインストール
```bash
npm install
```

## 利用方法

### 開発用サーバ起動
```bash
npm run dev
```
ローカルサーバが `http://localhost:7000` で起動します。

### ビルド
```bash
npm run build
```
`dist/` フォルダにビルド成果物が生成されます。

### プレビュー
```bash
npm run preview
```
ビルド済みの内容をローカルサーバで確認できます。

### デプロイ
```bash
npm run deploy
```
ビルドを実行後、`dist/` の内容を `../../server/public` へコピーします。

## フォルダ構成例
```
jsonpost-dashboard/
├── src/
│   ├── components/
│   ├── router/
│   ├── main.ts
│   ├── App.vue
├── public/
├── package.json
├── vite.config.ts
└── tsconfig.json
```

## 使用技術
- Vue 3
- TypeScript
- Vite
- Element Plus
- Less


