<template>
  <div class="status-tab">
    <el-card shadow="always">
      <h2>サーバーステータス</h2>
      <el-button type="primary" @click="fetchStatus" :loading="loading">更新</el-button>
      <el-divider></el-divider>
      <el-skeleton v-if="loading" :rows="5" animated />
      <pre v-else class="status-json">{{ statusJson }}</pre>
    </el-card>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { ElCard, ElButton, ElSkeleton, ElDivider } from 'element-plus';
import 'element-plus/dist/index.css';
import { apiBaseUrl } from '../config';

const status = ref(null);
const loading = ref(false);

const fetchStatus = async () => {
  loading.value = true;
  try {
    const response = await fetch(`${apiBaseUrl}/status.php`);
    const data = await response.json();
    status.value = data;
  } catch (error) {
    console.error('ステータス取得エラー:', error);
  } finally {
    loading.value = false;
  }
};

const statusJson = computed(() =>
  status.value ? JSON.stringify(status.value, null, 2) : 'まだデータがありません。更新を押してください。'
);

fetchStatus();
</script>

<style lang="less">
.status-tab {
  padding: 20px;
  .status-json {
    background: #f4f4f4;
    padding: 10px;
    border-radius: 6px;
    white-space: pre-wrap;
  }
  h2 {
    margin-bottom: 10px;
  }
}
</style>