<template>
  <div class="count-tab">
    <h2 class="page-title">統計情報</h2>
    <div class="form-section">
      <el-form class="form-area" label-position="top" @submit.prevent>
        <el-row :gutter="10" class="compact-row">
          <el-col :span="6">
            <el-form-item label="Offset" class="compact-form-item">
              <el-input v-model.number="offset" type="number" size="small" class="input-box" />
            </el-form-item>
          </el-col>
          <el-col :span="6">
            <el-form-item label="Limit" class="compact-form-item">
              <el-input v-model.number="limit" type="number" size="small" class="input-box" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="10" class="compact-row">
          <el-col :span="12">
            <el-form-item label="Path" class="compact-form-item">
              <el-input v-model="path" size="small" class="input-box-wide" placeholder="JSONPath式を入力してください (例: $.key)" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="10" class="compact-row">
          <el-col :span="12">
            <el-form-item label="Value" class="compact-form-item">
              <el-input v-model="value" size="small" class="input-box-wide" placeholder="検索する値を入力 (例: 123 または true)" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row class="compact-row">
          <el-col :span="4" class="search-button">
            <el-form-item class="compact-form-item">
              <el-button type="primary" @click="fetchCount" size="large">検索</el-button>
            </el-form-item>
          </el-col>
        </el-row>
      </el-form>
    </div>

    <div v-if="countData && countData.result" class="result-table">
      <table class="simple-table">
        <tr>
          <th>総レコード数</th>
          <td>{{ countData.result.total }}</td>
        </tr>
        <tr>
          <th>一致数</th>
          <td>{{ countData.result.matched }}</td>
        </tr>
        <tr>
          <th>範囲</th>
          <td>Offset: {{ countData.result.range.offset }} / Limit: {{ countData.result.range.limit }}</td>
        </tr>
      </table>
    </div>

    <div v-else-if="countData" class="result-text">データが見つかりませんでした。</div>

    <div v-else class="placeholder-text">統計情報を取得するには検索条件を入力して「検索」を押してください。</div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { ElForm, ElFormItem, ElInput, ElButton, ElRow, ElCol } from 'element-plus';
import 'element-plus/dist/index.css';
import { apiBaseUrl } from '../config';

const offset = ref(0);
const limit = ref(10);
const path = ref('');
const value = ref('');
const countData = ref(null);

const fetchCount = async () => {
  countData.value = null;
  const params = new URLSearchParams({
    offset: offset.value.toString(),
    limit: limit.value.toString(),
    ...(path.value && { path: path.value }),
    ...(value.value && { value: value.value }),
  });

  try {
    const response = await fetch(`${apiBaseUrl}/count.php?${params.toString()}`);
    const data = await response.json();
    console.log('取得結果:', data);
    countData.value = data;
  } catch (error) {
    console.error('統計取得エラー:', error);
  }
};
</script>

<style lang="less">
.count-tab {
  padding: 20px;

  .page-title {
    font-size: 24px;
    margin-bottom: 20px;
  }

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
    color: #333;
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

  .simple-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
    th, td {
      border: 1px solid #ddd;
      padding: 8px 12px;
      text-align: left;
    }
    th {
      background-color: #f4f4f4;
      font-weight: bold;
      width: 150px;
    }
  }
}
</style>
