<template>
  <div class="dashboard fade-in">
    <div class="page-header">
      <div class="page-header-left">
        <h2 class="page-title">Dashboard</h2>
      </div>
      <span class="page-subtitle">{{ today }}</span>
    </div>

    <div v-if="loading" class="loading-state">
      <div class="spinner"></div>
      <span>Loading dashboard...</span>
    </div>

    <div v-else-if="error" class="notice notice-danger">{{ error }}</div>

    <template v-else>
      <!-- Summary Cards -->
      <div class="stats-grid">
        <div class="card stat-card">
          <div class="stat-info">
            <span class="stat-label">Roll Lots</span>
            <span class="stat-value">{{ data.roll.total.toLocaleString() }}</span>
            <span class="stat-sub">{{ formatWeight(data.roll.total_weight) }} kg</span>
          </div>
          <div class="stat-icon icon-roll">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <ellipse cx="12" cy="7" rx="8" ry="4"/>
              <path d="M20 7v4c0 2.2-3.6 4-8 4s-8-1.8-8-4V7"/>
              <path d="M4 11v4c0 2.2 3.6 4 8 4s8-1.8 8-4v-4"/>
            </svg>
          </div>
        </div>

        <div class="card stat-card">
          <div class="stat-info">
            <span class="stat-label">Sheets</span>
            <span class="stat-value">{{ data.sheet.total.toLocaleString() }}</span>
            <span class="stat-sub">{{ formatWeight(data.sheet.total_weight) }} kg</span>
          </div>
          <div class="stat-icon icon-sheet">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
              <polyline points="14 2 14 8 20 8"/>
              <line x1="8" y1="13" x2="16" y2="13"/>
              <line x1="8" y1="17" x2="12" y2="17"/>
            </svg>
          </div>
        </div>

        <div class="card stat-card">
          <div class="stat-info">
            <span class="stat-label">Imports</span>
            <span class="stat-value">{{ data.imports.total.toLocaleString() }}</span>
            <div class="stat-sub badges-row">
              <span class="badge badge-success">{{ data.imports.success }} ok</span>
              <span class="badge badge-danger">{{ data.imports.failed }} fail</span>
            </div>
          </div>
          <div class="stat-icon icon-import">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
              <polyline points="7 10 12 15 17 10"/>
              <line x1="12" y1="15" x2="12" y2="3"/>
            </svg>
          </div>
        </div>

        <div class="card stat-card">
          <div class="stat-info">
            <span class="stat-label">Hari Ini</span>
            <span class="stat-value">{{ data.imports_today }}</span>
            <span class="stat-sub">import {{ data.imports_today === 1 ? 'file' : 'files' }}</span>
          </div>
          <div class="stat-icon icon-today">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
              <line x1="16" y1="2" x2="16" y2="6"/>
              <line x1="8" y1="2" x2="8" y2="6"/>
              <line x1="3" y1="10" x2="21" y2="10"/>
              <circle cx="12" cy="14" r="2"/>
            </svg>
          </div>
        </div>
      </div>

      <!-- Chart + Recent Imports Row -->
      <div class="dashboard-row">
        <!-- Activity Chart -->
        <div class="card chart-card">
          <div class="card-header">
            <h3>
              <svg class="inline-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
              </svg>
              Aktivitas Import (7 Hari)
            </h3>
          </div>
          <div class="card-pad">
            <div class="chart-wrapper">
              <canvas ref="chartCanvas"></canvas>
            </div>
            <div v-if="!hasChartData" class="chart-empty">
              <span>Belum ada data import minggu ini</span>
            </div>
          </div>
        </div>

        <!-- Recent Imports -->
        <div class="card recent-card">
          <div class="card-header">
            <h3>
              <svg class="inline-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                <polyline points="14 2 14 8 20 8"/>
              </svg>
              Import Terbaru
            </h3>
          </div>
          <div class="card-pad">
            <div v-if="data.imports.recent.length === 0" class="empty-inner">
              Belum ada import.
            </div>
            <div v-else class="recent-list">
              <div v-for="imp in data.imports.recent.slice(0, 6)" :key="imp.id" class="recent-item">
                <div class="recent-left">
                  <span :class="['type-dot', 'dot-' + imp.type]"></span>
                  <div class="recent-file-info">
                    <span class="recent-filename" :title="imp.filename">{{ imp.filename }}</span>
                    <span class="recent-date">{{ formatDate(imp.created_at) }}</span>
                  </div>
                </div>
                <div class="recent-right">
                  <span :class="['status-pill', 'pill-' + imp.status]">{{ imp.status }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, onMounted, computed, nextTick, onUnmounted } from 'vue';
import axios from 'axios';
import { Chart, BarController, CategoryScale, LinearScale, BarElement, Tooltip } from 'chart.js';

Chart.register(BarController, CategoryScale, LinearScale, BarElement, Tooltip);

const loading = ref(true);
const error = ref(null);
const data = ref(null);
const chartCanvas = ref(null);
let chartInstance = null;

const today = computed(() => {
  return new Date().toLocaleDateString('id-ID', {
    weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
  });
});

const hasChartData = computed(() => {
  return data.value?.imports?.recent?.length > 0;
});

function formatWeight(val) {
  if (val == null) return '0';
  return Number(val).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}

function formatDate(val) {
  if (!val) return '-';
  return new Date(val).toLocaleDateString('id-ID', {
    year: 'numeric', month: 'short', day: '2-digit',
    hour: '2-digit', minute: '2-digit',
  });
}

function buildChartData(recentImports) {
  const days = {};
  const now = new Date();
  for (let i = 6; i >= 0; i--) {
    const d = new Date(now);
    d.setDate(d.getDate() - i);
    const key = d.toLocaleDateString('en-CA');
    days[key] = { label: d.toLocaleDateString('id-ID', { weekday: 'short', day: 'numeric' }), count: 0 };
  }

  recentImports.forEach(imp => {
    const d = new Date(imp.created_at).toLocaleDateString('en-CA');
    if (days[d]) days[d].count++;
  });

  return {
    labels: Object.values(days).map(d => d.label),
    counts: Object.values(days).map(d => d.count),
  };
}

function renderChart() {
  if (!chartCanvas.value || !data.value?.imports?.recent?.length) return;

  if (chartInstance) chartInstance.destroy();

  const chartData = buildChartData(data.value.imports.recent);
  const ctx = chartCanvas.value.getContext('2d');

  chartInstance = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: chartData.labels,
      datasets: [{
        label: 'Imports',
        data: chartData.counts,
        backgroundColor: chartData.counts.map(v => v > 0 ? '#059669' : '#e7e8ef'),
        borderRadius: 6,
        borderSkipped: false,
        maxBarThickness: 40,
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: false }, tooltip: { enabled: true } },
      scales: {
        y: { beginAtZero: true, ticks: { stepSize: 1, color: '#a5a8b5' }, grid: { color: '#f1f2f6' } },
        x: { ticks: { color: '#a5a8b5', font: { size: 11 } }, grid: { display: false } }
      }
    }
  });
}

async function fetchSummary() {
  loading.value = true;
  error.value = null;
  try {
    const res = await axios.get('/api/dashboard');
    data.value = res.data;
    loading.value = false;
    await nextTick();
    renderChart();
  } catch (err) {
    loading.value = false;
    error.value = 'Gagal load dashboard: ' + err.message;
  }
}

onMounted(fetchSummary);

onUnmounted(() => {
  if (chartInstance) chartInstance.destroy();
});
</script>

<style scoped>
/* Stats grid */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
  gap: 1.25rem;
  margin-bottom: 1.25rem;
}
.stat-card {
  padding: 1.5rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  transition: transform var(--transition), box-shadow var(--transition);
}
.stat-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); }
.stat-info { display: flex; flex-direction: column; min-width: 0; }
.stat-label {
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--text-muted);
  margin-bottom: 0.35rem;
}
.stat-value { font-size: 1.75rem; font-weight: 700; color: var(--text-heading); line-height: 1.1; }
.stat-sub { font-size: 0.8rem; color: var(--text-muted); margin-top: 0.3rem; }
.badges-row { display: flex; gap: 0.4rem; flex-wrap: wrap; }

.stat-icon {
  width: 3.25rem;
  height: 3.25rem;
  border-radius: var(--radius-lg);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.stat-icon svg { width: 1.6rem; height: 1.6rem; }
.icon-roll { background: var(--primary-light); color: var(--primary); }
.icon-sheet { background: #eff6ff; color: #3b82f6; }
.icon-import { background: var(--warning-bg); color: var(--warning); }
.icon-today { background: #f0fdf4; color: #16a34a; }

/* Dashboard row */
.dashboard-row {
  display: grid;
  grid-template-columns: 3fr 2fr;
  gap: 1.25rem;
}
.chart-card, .recent-card { display: flex; flex-direction: column; }
.chart-wrapper { height: 240px; position: relative; }
.chart-empty { text-align: center; padding: 3rem 0; color: var(--text-muted); font-size: 0.9rem; }

/* Recent list */
.empty-inner { text-align: center; padding: 2.5rem; color: var(--text-muted); }
.recent-list { display: flex; flex-direction: column; }
.recent-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.75rem 0;
  border-bottom: 1px solid var(--border-light);
  gap: 0.5rem;
}
.recent-item:last-child { border-bottom: none; }
.recent-left { display: flex; align-items: center; gap: 0.75rem; min-width: 0; flex: 1; }
.type-dot { width: 9px; height: 9px; border-radius: 50%; flex-shrink: 0; }
.dot-roll { background: var(--primary); }
.dot-sheet { background: #d946ef; }
.recent-file-info { display: flex; flex-direction: column; min-width: 0; }
.recent-filename {
  font-size: 0.85rem;
  font-weight: 500;
  color: var(--text-heading);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 200px;
}
.recent-date { font-size: 0.75rem; color: var(--text-muted); }
.recent-right { display: flex; align-items: center; gap: 0.75rem; flex-shrink: 0; }

@media (max-width: 900px) {
  .dashboard-row { grid-template-columns: 1fr; }
}
</style>
