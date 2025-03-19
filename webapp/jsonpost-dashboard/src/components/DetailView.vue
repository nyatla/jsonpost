<template>
  <div class="detail-view">
    <h2 class="page-title">JSONファイルの詳細</h2>

    <div class="metadata-card">
      <table>
      <tbody>
              <tr><th>タイムスタンプ</th><td>{{ new Date(jsonData?.result?.timestamp).toLocaleString() }}</td></tr>
        <tr><th>UUID (アカウント)</th><td>{{ jsonData?.result?.uuid_account }}</td></tr>
        <tr><th>UUID (ドキュメント)</th><td>{{ jsonData?.result?.uuid_document }}</td></tr>
        <tr><th>パス</th><td>{{ jsonData?.result?.path }}</td></tr>
        <tr><th>PowStamp</th><td>{{ jsonData?.result?.powstamp }}</td></tr>
      </tbody>
      </table>
    </div>

    <div class="controls">
      <div class="control-row">
        <label>
          <input type="checkbox" v-model="prettyPrint" /> プリティプリント
        </label>
      </div>
      <div class="control-row">
        <label>
          JSONPath フィルタ:
          <input type="text" v-model="jsonPathFilter" placeholder="例: $.items[*].name" />
        </label>
        <button @click="applyJsonPathFilter">適用</button>
        <button @click="downloadJson" class="download-button">ダウンロード</button>
      </div>
    </div>

    <pre class="json-display">{{ formattedJson }}</pre>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRoute } from 'vue-router';
import { apiBaseUrl } from '../config';

const route = useRoute();
const uuid = route.params.uuid;
const jsonData = ref(null);
const prettyPrint = ref(true);
const jsonPathFilter = ref('');

const fetchDetail = async (filter = '') => {
  try {
    const response = await fetch(`${apiBaseUrl}/json.php?uuid=${uuid}${filter ? `&path=${encodeURIComponent(filter)}` : ''}`);
    const data = await response.json();
    jsonData.value = data;
  } catch (error) {
    console.error('詳細取得エラー:', error);
  }
};

const formattedJson = computed(() => {
  if (!jsonData.value?.result?.json) return 'データがありません';
  return prettyPrint.value
    ? JSON.stringify(jsonData.value.result.json, null, 2)
    : JSON.stringify(jsonData.value.result.json);
});

const applyJsonPathFilter = () => {
  fetchDetail(jsonPathFilter.value);
};

const downloadJson = () => {
  if (!jsonData.value?.result?.json) return;
  const blob = new Blob([
    prettyPrint.value
      ? JSON.stringify(jsonData.value.result.json, null, 2)
      : JSON.stringify(jsonData.value.result.json)
  ], { type: 'application/json' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = `${uuid}.json`;
  a.click();
  URL.revokeObjectURL(url);
};

onMounted(fetchDetail);
</script>

<style scoped>
.detail-view {
  padding: 20px;
}

.page-title {
  font-size: 24px;
  margin-bottom: 20px;
  font-weight: bold;
  color: #333;
}

.metadata-card {
  background-color: #fafafa;
  border: 1px solid #ddd;
  border-radius: 8px;
  padding: 16px;
  margin-bottom: 20px;
}

.metadata-card table {
  width: 100%;
  border-collapse: collapse;
}

.metadata-card th {
  text-align: left;
  background-color: #f0f0f0;
  padding: 6px 10px;
  width: 180px;
}

.metadata-card td {
  padding: 6px 10px;
  border-bottom: 1px solid #eee;
  word-break: break-word;
}

.controls {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-bottom: 20px;
}

.control-row {
  display: flex;
  gap: 12px;
  align-items: center;
}

.controls label {
  display: flex;
  align-items: center;
  gap: 8px;
  font-weight: bold;
  flex: 1;
}

.controls input[type="text"] {
  width: 100%;
  max-width: 500px;
  padding: 4px 8px;
  border: 1px solid #ccc;
  border-radius: 4px;
}

.controls button {
  padding: 8px 16px;
  border: none;
  border-radius: 4px;
  background-color: #409eff;
  color: white;
  cursor: pointer;
}

.controls button:hover {
  background-color: #66b1ff;
}

.download-button {
  margin-left: auto;
}

.json-display {
  background-color: #f4f4f4;
  padding: 20px;
  border-radius: 6px;
  white-space: pre-wrap;
  font-family: monospace;
  overflow-x: auto;
}
</style>
