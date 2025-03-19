<template>
  <div class="download-tab">
    <h2 class="page-title">クライアント入手先リンク</h2>
    <div class="download-section">
      <div class="download-item">
        <h3>GitHub リポジトリ</h3>
        <p>最新のソースコードとリリース情報:</p>
        <a href="https://github.com/nyatla/jsonpost" target="_blank" class="simple-link">GitHubを開く</a>
      </div>

      <div class="download-item">
        <h3>Python クライアント</h3>
        <p>最新のJSONPost Pythonクライアント:</p>
        <a href="/client/jsonpost-client-py.zip" target="_blank" class="simple-link">ダウンロード</a>
      </div>
    </div>
  </div>
</template>

<script setup>
</script>

<style lang="less">
.download-tab {
  padding: 20px;

  .page-title {
    font-size: 24px;
    margin-bottom: 20px;
  }

  .download-section {
    display: block;
    gap: 30px;

    .download-item {
      margin-bottom: 20px;
      background: #f9f9f9;
      border: 1px solid #ddd;
      border-radius: 8px;
      padding: 20px;

      h3 {
        margin-bottom: 10px;
        font-size: 18px;
      }

      p {
        margin-bottom: 12px;
        color: #444;
      }

      .simple-link {
        display: inline-block;
        color: #409eff;
        text-decoration: none;
        font-weight: bold;
        &:hover {
          text-decoration: underline;
        }
      }
    }
  }
}
</style>
