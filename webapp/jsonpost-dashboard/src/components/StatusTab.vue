<template>
  <div class="status-tab">
    <h2 class="page-title">サーバーステータス</h2>
    <div class="form-section">
      <el-button type="primary" @click="fetchStatus" :loading="loading">更新</el-button>
      <div v-if="loading" class="placeholder-text">ステータス情報を取得中です…</div>

      <div v-else-if="status">
        <table class="status-table">
          <tbody>

          <tr>
            <td class="label-cell">version</td>
            <td>{{ displayedStatus.settings.version }}</td>
          </tr>
          <tr>
            <td class="label-cell">pow_algorithm</td>
            <td>
              目標アクセス間隔: {{ displayedStatus.settings.pow_algorithm[1][0] }} 秒<br />
              目標アップロードサイズ: {{ displayedStatus.settings.pow_algorithm[1][1] }} KB<br />
              分布(σ値): {{ displayedStatus.settings.pow_algorithm[1][2] }}
            </td>
          </tr>
          <tr>
            <td class="label-cell">welcome</td>
            <td>{{ displayedStatus.settings.welcome }}</td>
          </tr>
          <tr>
            <td class="label-cell">json</td>
            <td>
              <table class="sub-table">
              <tbody>
                <tr>
                  <td class="label-cell">jcs</td>
                  <td>{{ displayedStatus.settings.json.jcs }}</td>
                </tr>
                <tr>
                  <td class="label-cell">schema</td>
                  <td>
                    <template v-if="displayedStatus.settings.json.schema">
                      <el-collapse>
                        <el-collapse-item title="スキーマ内容を表示" name="schema">
                          <pre>{{ displayedStatus.settings.json.schema }}</pre>
                        </el-collapse-item>
                      </el-collapse>
                    </template>
                    <template v-else>
                      null
                    </template>
                  </td>
                </tr>
              </tbody>
              </table>
            </td>
          </tr>
        </tbody>

        </table>

        <el-divider></el-divider>
        <el-collapse>
          <el-collapse-item title="元のJSONデータを表示" name="original-json">
            <pre class="status-json">{{ statusJson }}</pre>
          </el-collapse-item>
        </el-collapse>
      </div>

      <div v-else class="placeholder-text">まだデータがありません。更新を押してください。</div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { ElButton, ElCollapse, ElCollapseItem, ElDivider } from 'element-plus';
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

const displayedStatus = computed(() => status.value ? status.value.result : {});

const statusJson = computed(() =>
  status.value ? JSON.stringify(status.value, null, 2) : 'まだデータがありません。更新を押してください。'
);

fetchStatus();
</script>

<style lang="less">
.status-tab {
  padding: 20px;

  .page-title {
    font-size: 24px;
    margin-bottom: 20px;
  }

  .form-section {
    margin-bottom: 20px;
  }

  .status-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;

    td {
      padding: 8px 12px;
      text-align: left;
      border-bottom: 1px solid #ddd;
    }

    .label-cell {
      width: 1%;
      white-space: nowrap;
      font-weight: bold;
    }
  }

  .sub-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;

    td {
      padding: 4px 8px;
      text-align: left;
      border-bottom: 1px solid #eee;
    }

    .label-cell {
      width: 1%;
      white-space: nowrap;
      font-weight: bold;
    }
  }

  .status-json {
    background: #f4f4f4;
    padding: 10px;
    border-radius: 6px;
    white-space: pre-wrap;
    margin-top: 20px;
  }

  .placeholder-text {
    margin-top: 20px;
    padding: 20px;
    text-align: center;
    color: #666;
    background-color: #fafafa;
    border: 1px dashed #ccc;
    border-radius: 6px;
  }
}
</style>