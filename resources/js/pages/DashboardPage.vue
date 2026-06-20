<template>
  <div class="dashboard">
    <div class="page-header">
      <h2 class="page-title">Dashboard</h2>
      <span class="page-subtitle">{{ today }}</span>
    </div>

    <div v-if="loading" class="loading-state">
      <div class="spinner"></div>
      <span>Loading dashboard...</span>
    </div>

    <div v-else-if="error" class="error-msg">{{ error }}</div>

    <template v-else>
      <!-- Summary Cards -->
      <div class="cards-grid">
        <div class="card card-roll">
          <div class="card-icon-wrap">
            <svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <ellipse cx="12" cy="7" rx="8" ry="4"/>
              <path d="M20 7v4c0 2.2-3.6 4-8 4s-8-1.8-8-4V7"/>
              <path d="M4 11v4c0 2.2 3.6 4 8 4s8-1.8 8-4v-4"/>
            </svg>
          </div>
          <div class="card-body">
            <span class="card-label">Roll Lots</span>
            <span class="card-value">{{ data.roll.total.toLocaleString() }}</span>
            <span class="card-sub">{{ formatWeight(data.roll.total_weight) }} kg</span>
          </div>
        </div>

        <div class="card card-sheet">
          <div class="card-icon-wrap">
            <svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
              <polyline points="14 2 14 8 20 8"/>
              <line x1="8" y1="13" x2="16" y2="13"/>
              <line x1="8" y1="17" x2="12" y2="17"/>
            </svg>
          </div>
          <div class="card-body">
            <span class="card-label">Sheets</span>
            <span class="card-value">{{ data.sheet.total.toLocaleString() }}</span>
            <span class="card-sub">{{ formatWeight(data.sheet.total_weight) }} kg</span>
          </div>
        </div>

        <div class="card card-imports">
          <div class="card-icon-wrap">
            <svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
              <polyline points="7 10 12 15 17 10"/>
              <line x1="12" y1="15" x2="12" y2="3"/>
            </svg>
          </div>
          <div class="card-body">
            <span class="card-label">Imports</span>
            <span class="card-value">{{ data.imports.total.toLocaleString() }}</span>
            <div class="card-sub badges-row">
              <span class="badge badge-success">✓ {{ data.imports.success }}</span>
              <span class="badge badge-failed">✗ {{ data.imports.failed }}</span>
            </div>
          </div>
        </div>

        <div class="card card-today">
          <div class="card-icon-wrap">
            <svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
              <line x1="16" y1="2" x2="16" y2="6"/>
              <line x1="8" y1="2" x2="8" y2="6"/>
              <line x1="3" y1="10" x2="21" y2="10"/>
              <circle cx="12" cy="14" r="2"/>
            </svg>
          </div>
          <div class="card-body">
            <span class="card-label">Hari Ini</span>
            <span class="card-value">{{ data.imports_today }}</span>
            <span class="card-sub">import {{ data.imports_today === 1 ? 'file' : 'files' }}</span>
          </div>
        </div>
      </div>

      <!-- Chart + Recent Imports Row -->
      <div class="dashboard-row">
        <!-- Activity Chart -->
        <div class="card chart-card">
          <div class="card-header-inner">
            <h3>
              <svg class="inline-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
              </svg>
              Aktivitas Import (7 Hari)
            </h3>
          </div>
          <div class="chart-wrapper">
            <canvas ref="chartCanvas"></canvas>
          </div>
          <div v-if="!hasChartData" class="chart-empty">
            <span>Belum ada data import minggu ini</span>
          </div>
        </div>

        <!-- Recent Imports -->
        <div class="card recent-card">
          <div class="card-header-inner">
            <h3>
              <svg class="inline-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                <polyline points="14 2 14 8 20 8"/>
              </svg>
              Import Terbaru
            </h3>
          </div>
          <div v-if="data.imports.recent.length === 0" class="empty-state-inner">
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
                <span class="recent-counts">
                  <span class="count-ok">{{ imp.success_count ?? '-' }}</span>
                  <span v-if="imp.failed_count > 0" class="count-fail">{{ imp.failed_count }}</span>
                </span>
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
  // Group imports by date for last 7 days
  const days = {};
  const now = new Date();
  for (let i = 6; i >= 0; i--) {
    const d = new Date(now);
    d.setDate(d.getDate() - i);
    const key = d.toLocaleDateString('en-CA'); // YYYY-MM-DD
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
        backgroundColor: chartData.counts.map(v => v > 0 ? '#059669' : '#e2e8f0'),
        borderRadius: 4,
        borderSkipped: false,
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: false }, tooltip: { enabled: true } },
      scales: {
        y: { beginAtZero: true, ticks: { stepSize: 1, color: '#94a3b8' }, grid: { color: '#f1f5f9' } },
        x: { ticks: { color: '#94a3b8', font: { size: 11 } }, grid: { display: false } }
      }
    }
  });
}

async function fetchSummary() {
  loading.value = true;
  error.value = null;
  try {
    const res = await fetch('/api/dashboard');
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    data.value = await res.json();
    await nextTick();
    renderChart();
  } catch (err) {
    error.value = 'Gagal load dashboard: ' + err.message;
  } finally {
    loading.value = false;
  }
}

onMounted(fetchSummary);

onUnmounted(() => {
  if (chartInstance) chartInstance.destroy();
});
</script>

<style scoped>
/* ─── Page ─── */
.dashboard { padding: 0; }
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
}
.page-title { font-size: 1.75rem; font-weight: 700; color: #1e293b; }
.page-subtitle { font-size: 0.875rem; color: #94a3b8; }

/* ─── Loading / Error ─── */
.loading-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1rem;
  padding: 4rem;
  color: #64748b;
}
.spinner {
  width: 2rem;
  height: 2rem;
  border: 3px solid #e2e8f0;
  border-top-color: #059669;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
.error-msg { padding: 2rem; text-align: center; color: #ef4444; }

/* ─── Cards Grid ─── */
.cards-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
  gap: 1.25rem;
  margin-bottom: 1.5rem;
}
.card {
  background: white;
  border-radius: 0.75rem;
  padding: 1.5rem;
  display: flex;
  align-items: center;
  gap: 1.25rem;
  border: 1px solid #e2e8f0;
  transition: all 0.2s ease;
}
.card:hover {
  border-color: #059669;
  box-shadow: 0 4px 12px rgba(5, 150, 105, 0.1);
  transform: translateY(-2px);
}

/* ─── Icon ─── */
.card-icon-wrap {
  width: 3.5rem;
  height: 3.5rem;
  border-radius: 0.75rem;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.card-roll .card-icon-wrap { background: #ecfdf5; }
.card-roll .icon-svg { width: 1.75rem; height: 1.75rem; color: #059669; }

.card-sheet .card-icon-wrap { background: #eff6ff; }
.card-sheet .icon-svg { width: 1.75rem; height: 1.75rem; color: #3b82f6; }

.card-imports .card-icon-wrap { background: #fef3c7; }
.card-imports .icon-svg { width: 1.75rem; height: 1.75rem; color: #d97706; }

.card-today .card-icon-wrap { background: #f0fdf4; }
.card-today .icon-svg { width: 1.75rem; height: 1.75rem; color: #16a34a; }

/* ─── Card Body ─── */
.card-body { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.card-label { font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #94a3b8; margin-bottom: 0.25rem; }
.card-value { font-size: 2rem; font-weight: 800; color: #0f172a; line-height: 1.1; }
.card-sub { font-size: 0.8rem; color: #94a3b8; margin-top: 0.15rem; }
.badges-row { display: flex; gap: 0.4rem; flex-wrap: wrap; margin-top: 0.3rem; }

.badge {
  display: inline-block;
  padding: 0.1rem 0.5rem;
  border-radius: 4px;
  font-size: 0.75rem;
  font-weight: 600;
}
.badge-success { background: #dcfce7; color: #16a34a; }
.badge-failed { background: #fee2e2; color: #dc2626; }

/* ─── Dashboard Row (Chart + Recent) ─── */
.dashboard-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1.25rem;
}

/* ─── Chart Card ─── */
.chart-card { display: flex; flex-direction: column; }
.card-header-inner {
  padding-bottom: 1rem;
  border-bottom: 1px solid #f1f5f9;
  margin-bottom: 1rem;
}
.card-header-inner h3 {
  font-size: 0.95rem;
  font-weight: 600;
  color: #1e293b;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}
.inline-icon { color: #059669; }
.chart-wrapper { height: 200px; position: relative; }
.chart-empty {
  text-align: center;
  padding: 3rem 0;
  color: #94a3b8;
  font-size: 0.9rem;
}

/* ─── Recent Imports ─── */
.recent-card { display: flex; flex-direction: column; }
.empty-state-inner {
  text-align: center;
  padding: 3rem;
  color: #94a3b8;
}
.recent-list {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}
.recent-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.75rem 0;
  border-bottom: 1px solid #f8fafc;
  gap: 0.5rem;
}
.recent-item:last-child { border-bottom: none; }
.recent-left { display: flex; align-items: center; gap: 0.75rem; min-width: 0; flex: 1; }
.type-dot {
  width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0;
}
.dot-roll { background: #059669; }
.dot-sheet { background: #f59e0b; }
.recent-file-info { display: flex; flex-direction: column; min-width: 0; }
.recent-filename {
  font-size: 0.85rem;
  font-weight: 500;
  color: #1e293b;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 180px;
}
.recent-date { font-size: 0.75rem; color: #94a3b8; }
.recent-right { display: flex; align-items: center; gap: 0.75rem; flex-shrink: 0; }
.status-pill {
  display: inline-block;
  padding: 0.15rem 0.5rem;
  border-radius: 999px;
  font-size: 0.7rem;
  font-weight: 600;
  text-transform: capitalize;
}
.pill-success { background: #dcfce7; color: #16a34a; }
.pill-failed { background: #fee2e2; color: #dc2626; }
.pill-pending, .pill-processing { background: #fef3c7; color: #92400e; }
.recent-counts {
  font-size: 0.8rem;
  font-weight: 600;
  display: flex;
  gap: 0.3rem;
}
.count-ok { color: #16a34a; }
.count-fail { color: #dc2626; }

@media (max-width: 900px) {
  .dashboard-row { grid-template-columns: 1fr; }
}
</style>
