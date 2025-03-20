<template>
  <div class="dashboard">
    <h1 class="title">JSONPOST ダッシュボード</h1>

    <div v-if="errorMessage" class="error-message">
      <p>エラーコード: {{ errorCode }}</p>
      <p>{{ errorMessage }}</p>
      <div v-if="errorCode == 503" style="margin-top: 10px;">
        クライアントのダウンロード: <a href="/client/jsonpost-client-py.zip">こちら</a>
      </div>
    </div>

    <el-tabs v-else v-model="activeTab" class="custom-tabs" tab-position="top">
      <el-tab-pane label="ステータス" name="status">
        <StatusTab @error="handleError" />
      </el-tab-pane>
      <el-tab-pane label="統計情報" name="count">
        <CountTab />
      </el-tab-pane>
      <el-tab-pane label="データ検索" name="search">
        <ListTab />
      </el-tab-pane>
      <el-tab-pane label="クライアントの入手" name="client">
        <ClientDownloadTab />
      </el-tab-pane>
    </el-tabs>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { ElTabs, ElTabPane } from 'element-plus';
import 'element-plus/dist/index.css';
import StatusTab from './StatusTab.vue';
import ListTab from './ListTab.vue';
import CountTab from './CountTab.vue';
import ClientDownloadTab from './ClientDownloadTab.vue';

const activeTab = ref('status');
const errorMessage = ref(null);
const errorCode = ref(null);

const handleError = (error) => {
  if (error && error.code) {
    errorCode.value = error.code;
    errorMessage.value = error.message;
  } else {
    errorCode.value = '不明';
    errorMessage.value = '不明なエラーが発生しました。';
  }
};
</script>
