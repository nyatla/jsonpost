<template>
  <div class="list-tab">
    <el-card shadow="always">
      <h2>ドキュメントリスト</h2>
      <el-form class="form-area" label-position="top" @submit.prevent>
        <el-row :gutter="10" class="compact-row">
          <el-col :span="6">
            <el-form-item label="Offset" class="compact-form-item">
              <el-input v-model.number="offset" type="number" placeholder="0" size="small" class="input-box" />
            </el-form-item>
          </el-col>
          <el-col :span="6">
            <el-form-item label="Limit" class="compact-form-item">
              <el-input v-model.number="limit" type="number" placeholder="10" size="small" class="input-box" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="10" class="compact-row">
          <el-col :span="12">
            <el-form-item label="Path" class="compact-form-item">
              <el-input v-model="path" placeholder="$.key" size="small" class="input-box-wide" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="10" class="compact-row">
          <el-col :span="12">
            <el-form-item label="Value" class="compact-form-item">
              <el-input v-model="value" placeholder="検索値" size="small" class="input-box-wide" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row class="compact-row">
          <el-col :span="4" class="search-button">
            <el-form-item class="compact-form-item">
              <el-button type="primary" @click="fetchList" size="large">検索</el-button>
            </el-form-item>
          </el-col>
        </el-row>
      </el-form>

      <el-table
        v-if="listData && listData.result && listData.result.table && listData.result.table.rows.length > 0"
        :data="listData.result.table.rows.map(row => Object.fromEntries(row.map((val, idx) => [listData.result.table.head[idx], listData.result.table.head[idx] === 'timestamp' ? new Date(val).toLocaleString() : val])))"
        style="width: 100%; margin-top: 20px; font-size: 12px; border: none; table-layout: fixed;"
      >
        <el-table-column prop="timestamp" :width="160">
          <template #default="scope">
            <div style="white-space: nowrap; text-align: center;">
              {{ scope.row.timestamp }}<br />
              <el-button type="primary" size="small" @click.stop="openDetailWindow(scope.row)">開く</el-button>
            </div>
          </template>
        </el-table-column>
        <el-table-column>
          <template #default="scope">
            <table style="width: 100%; font-size: 10px; border-collapse: collapse; table-layout: fixed;">
              <tr v-for="(entry, index) in Object.entries(scope.row).filter(([k]) => k !== 'timestamp')" :key="index">
                <td style="font-weight: bold; padding: 2px 4px; width: 100px; border: 1px solid #ccc; white-space: nowrap;">{{ entry[0] }}</td>
                <td style="padding: 2px 4px; border: 1px solid #ccc; word-break: break-word; width: calc(100% - 100px);">{{ entry[1] }}</td>
              </tr>
            </table>
          </template>
        </el-table-column>
      </el-table>

      <div v-else-if="listData" class="result-text">データが見つかりませんでした。</div>

      <el-skeleton v-else :rows="5" animated />
    </el-card>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { ElCard, ElForm, ElFormItem, ElInput, ElButton, ElTable, ElTableColumn, ElSkeleton, ElRow, ElCol } from 'element-plus';
import 'element-plus/dist/index.css';
import { apiBaseUrl } from '../config';

const offset = ref(0);
const limit = ref(10);
const path = ref('');
const value = ref('');
const listData = ref(null);

const fetchList = async () => {
  listData.value = null;
  const params = new URLSearchParams({
    offset: offset.value.toString(),
    limit: limit.value.toString(),
    ...(path.value && { path: path.value }),
    ...(value.value && { value: value.value }),
  });

  try {
    const response = await fetch(`${apiBaseUrl}/list.php?${params.toString()}`);
    const data = await response.json();
    console.log('取得結果:', data);
    listData.value = data;
  } catch (error) {
    console.error('リスト取得エラー:', error);
  }
};

const openDetailWindow = (row) => {
  const uuid = row.uuid_document;
  if (uuid) {
    window.open(`/detail/${uuid}`, '_blank');
  }
};
</script>

<style lang="less">
.list-tab {
  padding: 20px;
  .form-area {
    margin-bottom: 20px;
    .compact-row {
      margin-bottom: 4px !important;
    }
  }
  .compact-form-item {
    margin-bottom: 6px !important;
  }
  .el-form-item__label {
    display: block;
    margin-bottom: 4px;
    font-weight: bold;
  }
  .search-button {
    display: flex;
    align-items: flex-end;

    .el-button {
      font-size: 18px;
      padding: 12px 28px;
      height: auto;
    }
  }
  .input-box {
    width: 100%;
    height: 32px;
  }
  .input-box-wide {
    width: 100%;
    height: 36px;
  }
  .result-text {
    margin-top: 20px;
    padding: 10px;
    background-color: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 4px;
    white-space: pre-wrap;
    font-family: monospace;
    color: #333;
  }
}
</style>
