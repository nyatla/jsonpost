<template>
    <div class="simulator-tab">
      <h2 class="page-title">難度シミュレーター</h2>
  
      <div class="inputs-container">
        <div class="input-group">
          <label>目標アクセス時刻 (time)</label>
          <el-input-number v-model="time" :min="0.01" :step="0.01" :precision="3" />
        </div>
        <div class="input-group">
          <label>サイズピーク (size_peak)</label>
          <el-input-number v-model="sizePeak" :min="0.001" :step="0.001" :precision="3" />
        </div>
        <div class="input-group">
          <label>サイズσ (size_sigma)</label>
          <el-input-number v-model="sizeSigma" :min="0.001" :step="0.01" :precision="3" />
        </div>
        <el-button @click="fetchAndApplyStatus">現在ステータスから反映</el-button>
      </div>
  
      <div class="charts-row">
        <div class="chart-section" style="height: 200px;">
          <v-chart ref="timeChart" :option="timeChartOption" autoresize style="width: 100%; height: 100%;" />
        </div>
        <div class="chart-section" style="height: 200px;">
          <v-chart ref="sizeChart" :option="sizeChartOption" autoresize style="width: 100%; height: 100%;" />
        </div>
      </div>
  
      <div class="chart-section large" style="height: 300px;">
        <v-chart ref="heatmapChart" :option="heatmapOption" autoresize style="width: 100%; height: 100%;" />
      </div>
    </div>
  </template>
  
  <script setup lang="ts">
  import { ref, watch, onMounted, nextTick, onBeforeUnmount } from 'vue';
  import { ElInputNumber, ElButton } from 'element-plus';
  import VChart from 'vue-echarts';
  import { use } from 'echarts/core';
  import { CanvasRenderer } from 'echarts/renderers';
  import { LineChart, HeatmapChart } from 'echarts/charts';
  import { GridComponent, VisualMapComponent, TooltipComponent, DatasetComponent } from 'echarts/components';
  import { TimeLogiticsSizeLnDifficulty, LogiticsRateProvider, LogNormalRateProvider } from '../libs/rateProviders';
  import { apiBaseUrl } from '../config';
  
  use([
    CanvasRenderer,
    LineChart,
    HeatmapChart,
    GridComponent,
    VisualMapComponent,
    TooltipComponent,
    DatasetComponent
  ]);
  
  const time = ref<number>(10);
  const sizePeak = ref<number>(16);
  const sizeSigma = ref<number>(0.8);
  
  const timeChartOption = ref<Record<string, unknown>>({});
  const sizeChartOption = ref<Record<string, unknown>>({});
  const heatmapOption = ref<Record<string, unknown>>({});
  
  const timeChart = ref<InstanceType<typeof VChart> | null>(null);
  const sizeChart = ref<InstanceType<typeof VChart> | null>(null);
  const heatmapChart = ref<InstanceType<typeof VChart> | null>(null);
  
  const fetchAndApplyStatus = async () => {
    const res = await fetch(`${apiBaseUrl}/status.php`);
    const json = await res.json();
    if (json.success) {
      const alg = json.result.settings.pow_algorithm;
      time.value = alg[1][0];
      sizePeak.value = alg[1][1];
      sizeSigma.value = alg[1][2];
    }
  };
  
  const throttle = (fn: (...args: unknown[]) => void, delay: number) => {
    let lastCall = 0;
    return (...args: unknown[]) => {
      const now = new Date().getTime();
      if (now - lastCall < delay) return;
      lastCall = now;
      fn(...args);
    };
  };
  
  const updateCharts = throttle(() => {
    const tProvider = new LogiticsRateProvider(time.value / 2);
    const tMax = (time.value / 2) * 100;
    const tX: number[] = [], tY: number[] = [];
    for (let i = 0; i <= 100; i++) {
      const tVal = Math.exp(Math.log(0.1) + (Math.log(tMax) - Math.log(0.1)) * (i / 100));
      tX.push(tVal);
      tY.push(tProvider.rate(tVal));
    }
    timeChartOption.value = {
      grid: { containLabel: true, left: 40, right: 20, top: 50, bottom: 40 },
      xAxis: { type: 'log' },
      yAxis: {},
      series: [{ data: tX.map((x, i) => [x, tY[i]]), type: 'line', smooth: true }],
    };
  
    const sProvider = new LogNormalRateProvider(sizePeak.value, sizeSigma.value);
    const sX: number[] = [], sY: number[] = [];
    for (let i = 0; i <= 100; i++) {
      const sVal = Math.exp(Math.log(sizePeak.value / 10) + (Math.log(sizePeak.value * 100) - Math.log(sizePeak.value / 10)) * (i / 100));
      sX.push(sVal);
      sY.push(sProvider.rate(sVal));
    }
    sizeChartOption.value = {
      grid: { containLabel: true, left: 40, right: 20, top: 50, bottom: 40 },
      xAxis: { type: 'log' },
      yAxis: {},
      series: [{ data: sX.map((x, i) => [x, sY[i]]), type: 'line', smooth: true }],
    };
  
    const difficulty = new TimeLogiticsSizeLnDifficulty(time.value * 0.5, sizePeak.value, sizeSigma.value);
    const tSteps = 50, sSteps = 100;
    const heatmapData: [number, number, number][] = [];
    const tLabels: string[] = [];
    const sLabels: string[] = [];

    for (let i = 0; i < tSteps; i++) {
      const t = Math.exp(Math.log(0.1) + (Math.log(tMax) - Math.log(0.1)) * (i / (tSteps - 1)));
      tLabels.push(t.toFixed(2));
      for (let j = 0; j < sSteps; j++) {
        if (i === 0) {
          const s = Math.exp(Math.log(sizePeak.value / 100) + (Math.log(sizePeak.value * 100) - Math.log(sizePeak.value / 100)) * (j / (sSteps - 1)));
          sLabels.push(s.toFixed(2));
        }
        const s = Math.exp(Math.log(sizePeak.value / 100) + (Math.log(sizePeak.value * 100) - Math.log(sizePeak.value / 100)) * (j / (sSteps - 1)));
        heatmapData.push([i, j, difficulty.rate(t, s)]);
      }
    }
    heatmapOption.value = {
      grid: { containLabel: true, left: 60, right: 40, top: 60, bottom: 60 },
      tooltip: {},
      xAxis: { type: 'category', data: tLabels },
      yAxis: { type: 'category', data: sLabels },
      visualMap: { min: 0, max: 1, orient: 'vertical', left: 'right', top: 'center' },
      series: [{ type: 'heatmap', data: heatmapData, emphasis: { focus: 'series' } }],
    };
  
    nextTick(() => {
      timeChart.value?.resize();
      sizeChart.value?.resize();
      heatmapChart.value?.resize();
    });
  }, 200);
  
  watch([time, sizePeak, sizeSigma], updateCharts);
  onMounted(updateCharts);
  onBeforeUnmount(() => {
    window.removeEventListener('resize', updateCharts);
  });
  </script>
  
  <style scoped lang="less">
  .simulator-tab {
    padding: 20px;
    max-width: 1400px;
    margin: auto;
  
    .page-title {
      font-size: 24px;
      margin-bottom: 20px;
      text-align: left;
    }
  
    .inputs-container {
      display: flex;
      flex-wrap: wrap;
      gap: 20px;
      align-items: flex-end;
      margin-bottom: 30px;
  
      .input-group {
        display: flex;
        flex-direction: column;
        gap: 4px;
        label {
          font-weight: bold;
        }
      }
  
      button {
        height: 40px;
      }
    }
  
    .charts-row {
      display: flex;
      flex-wrap: wrap;
      gap: 30px;
      justify-content: space-between;
      margin-bottom: 50px;
  
      .chart-section {
        flex: 1 1 48%;
        min-width: 300px;
        height: 200px;
      }
    }
  
    .chart-section.large {
      width: 100%;
      height: 300px;
    }
  }
  </style>