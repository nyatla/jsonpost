<template>
    <div class="dashboard">
      <h1 class="title">JSONPOST ダッシュボード</h1>
      <el-tabs v-model="activeTab" class="custom-tabs">
        <el-tab-pane label="ステータス" name="status">
          <StatusTab />
        </el-tab-pane>
        <el-tab-pane label="JSONPath検索" name="search">
          <SearchTab />
        </el-tab-pane>
        <el-tab-pane label="リスト表示" name="list">
          <ListTab />
        </el-tab-pane>
        <el-tab-pane label="統計情報" name="count">
          <CountTab />
        </el-tab-pane>
        <el-tab-pane label="PowStamp クライアント" name="client">
          <ClientDownloadTab />
        </el-tab-pane>
      </el-tabs>
    </div>
  </template>
  
  <script setup>
  import { ref } from 'vue';
  import { ElTabs, ElTabPane, ElSkeleton } from 'element-plus';
  import 'element-plus/dist/index.css';
  import StatusTab from './StatusTab.vue';
  import SearchTab from './SearchTab.vue';
  import ListTab from './ListTab.vue';
  import CountTab from './CountTab.vue';
  import ClientDownloadTab from './ClientDownloadTab.vue';
  
  const activeTab = ref('status');
  </script>
  
  <style lang="less">
  .dashboard {
    padding: 20px;
    .title {
      font-size: 28px;
      font-weight: bold;
      margin-bottom: 20px;
    }
  }
  
  .custom-tabs {
    .el-tabs__item {
      padding: 10px 20px;
      font-size: 16px;
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
  