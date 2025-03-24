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
          <StatusTab @error="handleError" :active="activeTab === 'status'" />
        </el-tab-pane>
        <el-tab-pane label="統計情報" name="count">
          <CountTab :active="activeTab === 'count'"/>
        </el-tab-pane>
        <el-tab-pane label="データ検索" name="search">
          <ListTab :active="activeTab === 'search'"/>
        </el-tab-pane>
        <el-tab-pane label="クライアントの入手" name="client">
          <ClientDownloadTab :active="activeTab === 'client'"/>
        </el-tab-pane>
        <el-tab-pane label="難度シミュレータ" name="simulator">
            <DifficultySimulatorTab :active="activeTab === 'simulator'" />
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
  import DifficultySimulatorTab from './DifficultySimulatorTab.vue';
  
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
  
  <style scoped lang="less">
  .dashboard {
    padding: 20px;
    max-width: 1200px;
    margin: auto;
  
    .title {
      font-size: 28px;
      font-weight: bold;
      margin-bottom: 20px;
      text-align: center;
    }
  
    .error-message {
      margin-top: 40px;
      padding: 20px;
      border: 1px solid #ff4d4f;
      background: #fff2f0;
      color: #d32f2f;
      font-weight: bold;
      border-radius: 6px;
      text-align: center;
      font-size: 18px;
      line-height: 1.6;
    }
  }
  </style>
  