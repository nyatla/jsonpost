<template>
    <div class="simulator-tab">
      <h2 class="page-title">難度シミュレーター</h2>
  
      <div class="inputs-container">
        <div class="input-group">
          <label>目標アクセス時刻 (time)</label>
          <el-input-number v-model="time" :min="0.1" :step="0.1" :precision="3" />
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
        <div class="chart-section">
          <h3>時間ロジスティック関数（秒）</h3>
          <canvas ref="timeChartCanvas" height="200"></canvas>
        </div>
        <div class="chart-section">
          <h3>サイズ対数正規分布関数</h3>
          <canvas ref="sizeChartCanvas" height="200"></canvas>
        </div>
      </div>
  
      <div class="chart-section large">
        <h3>TimeLogiticsSizeLnDifficulty ヒートマップ</h3>
        <canvas ref="heatmapCanvas" height="500" width="1000"></canvas>
      </div>
    </div>
  </template>
  
  <script setup lang="ts">
  import { ref, watch, nextTick, onMounted } from 'vue';
  import { ElInputNumber, ElButton } from 'element-plus';
  import { Chart } from 'chart.js/auto';
  import { MatrixController, MatrixElement } from 'chartjs-chart-matrix';
  import { TimeLogiticsSizeLnDifficulty, LogiticsRateProvider, LogNormalRateProvider } from '../libs/rateProviders';
  import { apiBaseUrl } from '../config';
  
  Chart.register(MatrixController, MatrixElement);
  
  const time = ref(10); // ← time_half の代わりに time（API値そのまま）
  const sizePeak = ref(16);
  const sizeSigma = ref(0.8);
  
  const timeChartCanvas = ref<HTMLCanvasElement | null>(null);
  const sizeChartCanvas = ref<HTMLCanvasElement | null>(null);
  const heatmapCanvas = ref<HTMLCanvasElement | null>(null);
  
  let timeChart: Chart | null = null;
  let sizeChart: Chart | null = null;
  let heatmapChart: Chart | null = null;
  
  const fetchAndApplyStatus = async () => {
    try {
      const res = await fetch(`${apiBaseUrl}/status.php`);
      const json = await res.json();
      if (json.success) {
        const alg = json.result.settings.pow_algorithm;
        time.value = alg[1][0]; // 倍値をそのまま time に設定
        sizePeak.value = alg[1][1];
        sizeSigma.value = alg[1][2];
      } else {
        alert(`ステータス取得エラー: ${json.error.message}`);
      }
    } catch {
      alert('ステータス取得に失敗しました。');
    }
  };
  
  const drawTimeChart = async () => {
    if (timeChart) timeChart.destroy();
    await nextTick();
  
    const halfTime = time.value / 2; // 内部計算で0.5倍
    const provider = new LogiticsRateProvider(halfTime);
    const data = [];
    const tMax = halfTime * 100;
    const steps = 100;
    for (let i = 0; i <= steps; i++) {
      const t = Math.exp(Math.log(0.1) + (Math.log(tMax) - Math.log(0.1)) * (i / steps));
      data.push({ x: t, y: provider.rate(t) });
    }
  
    timeChart = new Chart(timeChartCanvas.value!.getContext('2d')!, {
      type: 'line',
      data: {
        datasets: [{
          label: '時間ロジスティック',
          data,
          borderWidth: 2,
          fill: false,
          tension: 0,
          pointRadius: 0,
        }],
      },
      options: {
        responsive: true,
        animation: false,
        scales: {
          x: { type: 'logarithmic', title: { display: true, text: '時間（秒）' } },
          y: { title: { display: true, text: 'rate' }, min: 0, max: 1 },
        },
      },
    });
  };
  
  const drawSizeChart = async () => {
    if (sizeChart) sizeChart.destroy();
    await nextTick();
  
    const provider = new LogNormalRateProvider(sizePeak.value, sizeSigma.value);
    const data = [];
    const sMin = sizePeak.value / 10;
    const sMax = sizePeak.value * 100;
    const steps = 100;
    for (let i = 0; i <= steps; i++) {
      const s = Math.exp(Math.log(sMin) + (Math.log(sMax) - Math.log(sMin)) * (i / steps));
      data.push({ x: s, y: provider.rate(s) });
    }
  
    sizeChart = new Chart(sizeChartCanvas.value!.getContext('2d')!, {
      type: 'line',
      data: {
        datasets: [{
          label: 'サイズ分布',
          data,
          borderWidth: 2,
          fill: false,
          tension: 0,
          pointRadius: 0,
        }],
      },
      options: {
        responsive: true,
        animation: false,
        scales: {
          x: { type: 'logarithmic', title: { display: true, text: 'サイズ (KB)' } },
          y: { title: { display: true, text: 'rate' }, min: 0, max: 1 },
        },
      },
    });
  };
  
  const drawHeatmap = async () => {
  if (heatmapChart) heatmapChart.destroy();
  await nextTick();

  const difficulty = new TimeLogiticsSizeLnDifficulty(time.value * 0.5, sizePeak.value, sizeSigma.value);
  const data = [];

  const tSteps = 50;
  const sSteps = 100;
  const tMin = 0.1;
  const tMax = time.value * 0.5 * 100;

  const sMin = Math.max(sizePeak.value / 100, 0.001);
  const sMax = sizePeak.value * 100;

  for (let i = 0; i < tSteps; i++) {
    const t = Math.exp(Math.log(tMin) + (Math.log(tMax) - Math.log(tMin)) * (i / (tSteps - 1)));
    for (let j = 0; j < sSteps; j++) {
      const frac = 1 - (j / (sSteps - 1));
      const s = Math.exp(Math.log(sMin) + (Math.log(sMax) - Math.log(sMin)) * frac);

      const z = difficulty.rate(t, s);
      data.push({
        x: t,
        y: j,
        v: z,
        realSize: s
      });
    }
  }

  heatmapChart = new Chart(heatmapCanvas.value!.getContext('2d')!, {
    type: 'matrix',
    data: {
      datasets: [{
        label: 'ヒートマップ',
        data: data.map(p => ({
          x: p.x,
          y: p.y,
          v: p.v,
          width: (tMax - tMin) / tSteps,
          height: 1,
        })),
        backgroundColor: (ctx) => {
          const v = ctx.raw.v;
          let r = 0, g = 0, b = 0;

          if (v < 0.33) {
            const ratio = v / 0.33;
            r = Math.floor(255 * ratio);
          } else if (v < 0.66) {
            const ratio = (v - 0.33) / 0.33;
            r = 255;
            g = Math.floor(255 * ratio);
          } else {
            const ratio = (v - 0.66) / 0.34;
            r = Math.floor(255 * (1 - ratio));
            g = 255;
          }

          return `rgb(${r},${g},${b})`;
        },
        borderWidth: 0,
      }],
    },
    options: {
      responsive: true,
      animation: false,
      scales: {
        x: {
          type: 'logarithmic',
          min: tMin,
          max: tMax,
          title: { display: true, text: '時間（秒）' },
          ticks: { padding: 30 },
          grid: { display: false }
        },
        y: {
          type: 'linear',
          min: 0,
          max: sSteps - 1,
          title: { display: true, text: 'サイズ (KB)' },
          ticks: {
            callback: (val) => {
              const frac = 1 - (val as number) / (sSteps - 1);
              const sVal = Math.exp(Math.log(sMin) + (Math.log(sMax) - Math.log(sMin)) * frac);
              return sVal < 1 ? sVal.toFixed(3) : `${Math.round(sVal)}`;
            },
            padding: 20,
          },
          grid: { display: false }
        },
      },
      layout: { padding: { top: 50, bottom: 30 } },
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            title: (ctx) => {
              const frac = 1 - ctx[0].raw.y / (sSteps - 1);
              const sVal = Math.exp(Math.log(sMin) + (Math.log(sMax) - Math.log(sMin)) * frac);
              return `時間: ${ctx[0].raw.x.toFixed(2)}s, サイズ: ${sVal < 1 ? sVal.toFixed(3) : Math.round(sVal)}KB`;
            },
            label: (ctx) => `難易度: ${ctx.raw.v.toFixed(2)} （1=易 / 0=難）`,
          }
        }
      }
    },
  });
};





  
  watch([time, sizePeak, sizeSigma], () => {
    drawTimeChart();
    drawSizeChart();
    drawHeatmap();
  });
  
  onMounted(() => {
    drawTimeChart();
    drawSizeChart();
    drawHeatmap();
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
  
        canvas {
          width: 100%;
        }
      }
    }
  
    .chart-section.large {
      canvas {
        width: 100%;
      }
    }
  }
  </style>
  