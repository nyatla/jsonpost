<template>
  <div class="detail-view">
    <h2 class="page-title">JSONファイルの詳細</h2>

    <div class="metadata-card">
      <table class="info-table">
        <tbody>
          <tr>
            <th>PowStampMessage Hash</th>
            <td>{{ powstampMessageDetails?.hash }}</td>
          </tr>
          <tr>
            <th>タイムスタンプ</th>
            <td>{{ new Date(jsonData?.result?.timestamp).toLocaleString() }}</td>
          </tr>
          <tr>
            <th>UUID (アカウント)</th>
            <td>{{ jsonData?.result?.uuid_account }}</td>
          </tr>
          <tr>
            <th>UUID (ドキュメント)</th>
            <td>{{ jsonData?.result?.uuid_document }}</td>
          </tr>
          <tr>
            <th>パス</th>
            <td>{{ jsonData?.result?.path }}</td>
          </tr>
          <tr>
            <th>PowStampMessage</th>
            <td>
              <div class="break-word">{{ jsonData?.result?.powstampmessage }}</div>
              <table v-if="powstampMessageDetails" class="nested-detail-table small-nested-text">
                <tr><td>ECDSA 公開鍵</td><td>{{ powstampMessageDetails.pubkey }}</td></tr>
                <tr><td>Nonce (U48)</td><td>{{ powstampMessageDetails.nonce }}</td></tr>
                <tr><td>ServerDomainHash</td><td>{{ powstampMessageDetails.serverHash }}</td></tr>
                <tr><td>PayloadHash</td><td>{{ powstampMessageDetails.payloadHash }}</td></tr>
                <tr><td>PowScore (U48)</td><td>{{ powstampMessageDetails.powScore }}</td></tr>
              </table>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="controls">
      <div class="control-row">
        <label class="checkbox-label">
          <input type="checkbox" v-model="prettyPrint" /> プリティプリント
        </label>
      </div>
      <div class="control-row-buttons">
        <input type="text" v-model="jsonPathFilter" placeholder="例: $.items[*].name" class="filter-input" />
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
import { PowStamp2Message } from '../libs/PowStamp2Message';

const route = useRoute();
const uuid = route.params.uuid;
const jsonData = ref(null);
const prettyPrint = ref(true);
const jsonPathFilter = ref('');
const powstampMessageDetails = ref(null);

const fetchDetail = async (filter = '') => {
  try {
    const response = await fetch(`${apiBaseUrl}/json.php?uuid=${uuid}${filter ? `&path=${encodeURIComponent(filter)}` : ''}`);
    const data = await response.json();
    jsonData.value = data;

    if (data.result?.powstampmessage) {
      const message = new PowStamp2Message(data.result.powstampmessage);
      powstampMessageDetails.value = {
        pubkey: PowStamp2Message.bytesToHex(message.getEcdsaPubkey()),
        nonce: message.getNonceAsU48().toString(),
        serverHash: PowStamp2Message.bytesToHex(message.getServerDomainHash()),
        payloadHash: PowStamp2Message.bytesToHex(message.getPayloadHash()),
        powScore: (await message.getPowScoreU48()).toString(),
        hash: PowStamp2Message.bytesToHex(await message.getSha256d()),
      };
    }
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
.metadata-card {
  background-color: #fafafa;
  border: 1px solid #ddd;
  border-radius: 8px;
  padding: 16px;
  margin-bottom: 20px;
}

.info-table, .nested-detail-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 12px;
}

.info-table th, .info-table td, .nested-detail-table td {
  border: 1px solid #ccc;
  padding: 4px 6px;
  word-break: break-word;
}

.info-table th {
  background-color: #f0f0f0;
  text-align: left;
  width: 180px;
}

.nested-detail-table {
  margin-top: 8px;
  background-color: #fff;
  font-size: 10px;
}

.small-nested-text td {
  font-size: 10px;
}

.controls {
  display: flex;
  flex-direction: column;
  gap: 16px;
  margin-bottom: 20px;
}

.control-row-buttons {
  display: flex;
  gap: 10px;
  align-items: center;
  flex-wrap: wrap;
}

.filter-input {
  flex-grow: 1;
  padding: 6px 8px;
  border: 1px solid #ccc;
  border-radius: 4px;
  min-width: 240px;
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

.checkbox-label {
  display: flex;
  align-items: center;
  gap: 6px;
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