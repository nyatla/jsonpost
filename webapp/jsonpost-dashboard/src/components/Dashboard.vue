<template>
    <div class="dashboard">
      <h1 class="title">JSONPOST ダッシュボード</h1>
  
      <!-- エラーメッセージのみ表示 -->
      <div v-if="errorMessage" class="error-message">
        <p>エラーコード: {{ errorCode }}</p>
        <p>{{ errorMessage }}</p>
      </div>
  
      <!-- 通常タブ表示 -->
      <el-tabs v-else v-model="activeTab" class="custom-tabs" tab-position="top">
        <div class="tabs-center">
          <el-tab-pane label="ステータス" name="status">
            <StatusTab @error="handleError" />
          </el-tab-pane>
          <el-tab-pane label="統計情報" name="count">
            <CountTab @error="handleError" />
          </el-tab-pane>
          <el-tab-pane label="データ検索" name="search">
            <ListTab @error="handleError" />
          </el-tab-pane>
          <el-tab-pane label="クライアントの入手" name="client">
            <ClientDownloadTab @error="handleError" />
          </el-tab-pane>
        </div>
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
  
  <style lang="less">
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
  
  .custom-tabs {
    .el-tabs__nav-wrap::after {
      display: none;
    }
  
    .el-tabs__nav {
      display: flex;
      justify-content: center;
    }
  
    .el-tabs__item {
      width: 160px;
      text-align: center;
      padding: 10px 0;
      font-size: 16px;
      flex: none;
    }
  
    .el-tabs__item.is-active {
      font-weight: bold;
      background-color: #f0f0f0;
      border-radius: 4px 4px 0 0;
    }
  
    .el-tabs__content {
      padding: 20px;
      border: 1px solid #dcdfe6;
      background-color: #ffffff;
    }
  }
  </style>
  