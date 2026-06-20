<template>
  <div class="dashboard">
    <h2 class="page-title">Dashboard</h2>

    <div v-if="loading" class="loading">Loading...</div>

    <div v-else-if="error" class="error-msg">{{ error }}</div>

    <template v-else>
      <!-- Summary Cards Row -->
      <div class="cards-grid">
        <!-- Roll Lots Card -->
        <div class="card">
          <div class="card-icon roll-icon">📦</div>
          <div class="card-body">
            <div class="card-label">Total Roll Lots</div>
            <div class="card-value">{{ data.roll.total.toLocaleString() }}</div>
            <div class="card-sub">{{ formatWeight(data.roll.total_weight) }} kg total weight</div>
          </div>
        </div>

        <!-- Sheets Card -->
        <div class="card">
          <div class="card-icon sheet-icon">📄</div>
          <div class="card-body">
            <div class="card-label">Total Sheets</div>
            <div class="card-value">{{ data.sheet.total.toLocaleString() }}</div>
            <div class="card-sub">{{ formatWeight(data.sheet.total_weight) }} kg total weight</div>
          </div>
        </div>

        <!-- Imports Card -->
        <div class="card">
          <div class="card-icon import-icon">📥</div>
          <div class="card-body">
            <div class="card-label">Imports</div>
            <div class="card-value">{{ data.imports.total.toLocaleString() }}</div>
            <div class="card-sub">
              <span class="badge badge-success">✓ {{ data.imports.success }}</span>
              <span class="badge badge-failed">✗ {{ data.imports.failed }}</span>
            </div>
          </div>
        </div>

        <!-- Today Card -->
        <div class="card">
          <div class="card-icon today-icon">📅</div>
          <div class="card-body">
            <div class="card-label">Imports Today</div>
            <div class="card-value">{{ data.imports_today }}</div>
            <div class="card-sub">{{ today }}</div>
          </div>
        </div>
      </div>

      <!-- Recent Imports Table -->
      <div class="card recent-card">
        <div class="card-header">
          <h3>Recent Imports</h3>
        </div>
        <div v-if="data.imports.recent.length === 0" class="empty-state">
          No imports yet.
        </div>
        <table v-else class="recent-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Filename</th>
              <th>Type</th>
              <th>Status</th>
              <th>Rows</th>
              <th>Success</th>
              <th>Failed</th>
              <th>Date</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="imp in data.imports.recent" :key="imp.id">
              <td class="cell-id">{{ imp.id }}</td>
              <td class="cell-filename" :title="imp.filename">{{ imp.filename }}</td>
              <td>
                <span class="type-badge" :class="'type-' + imp.type">{{ imp.type }}</span>
              </td>
              <td>
                <span class="status-badge" :class="'status-' + imp.status">{{ imp.status }}</span>
              </td>
              <td>{{ imp.total_rows ?? '-' }}</td>
              <td class="cell-success">{{ imp.success_count ?? '-' }}</td>
              <td class="cell-failed">{{ imp.failed_count ?? '-' }}</td>
              <td class="cell-date">{{ formatDate(imp.created_at) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';

const loading = ref(true);
const error = ref(null);
const data = ref(null);

const today = computed(() => {
  return new Date().toLocaleDateString('id-ID', {
    weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
  });
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

async function fetchSummary() {
  loading.value = true;
  error.value = null;
  try {
    const res = await fetch('/api/dashboard');
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    data.value = await res.json();
  } catch (err) {
    error.value = 'Failed to load dashboard data: ' + err.message;
  } finally {
    loading.value = false;
  }
}

onMounted(fetchSummary);
</script>

<style scoped>
.dashboard {
  padding: 0;
}

.page-title {
  font-size: 1.75rem;
  font-weight: 700;
  color: #1e293b;
  margin-bottom: 1.5rem;
}

.loading,
.error-msg {
  padding: 2rem;
  text-align: center;
  color: #64748b;
}

.error-msg {
  color: #ef4444;
}

/* Cards grid */
.cards-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 1.25rem;
  margin-bottom: 1.5rem;
}

.card {
  background: white;
  border-radius: 10px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.08);
  padding: 1.25rem 1.5rem;
  display: flex;
  align-items: flex-start;
  gap: 1rem;
  border: 1px solid #e5e7eb;
}

.card-icon {
  font-size: 2rem;
  line-height: 1;
  flex-shrink: 0;
}

.card-body {
  flex: 1;
  min-width: 0;
}

.card-label {
  font-size: 0.8rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #64748b;
  margin-bottom: 0.25rem;
}

.card-value {
  font-size: 2rem;
  font-weight: 700;
  color: #1e40af;
  line-height: 1.1;
}

.card-sub {
  font-size: 0.8rem;
  color: #94a3b8;
  margin-top: 0.25rem;
  display: flex;
  gap: 0.4rem;
  flex-wrap: wrap;
}

/* Badges */
.badge {
  display: inline-block;
  padding: 0.1rem 0.45rem;
  border-radius: 4px;
  font-size: 0.75rem;
  font-weight: 600;
}

.badge-success {
  background: #dcfce7;
  color: #16a34a;
}

.badge-failed {
  background: #fee2e2;
  color: #dc2626;
}

/* Recent imports card */
.recent-card {
  display: block;
  padding: 0;
  overflow: hidden;
}

.card-header {
  padding: 1rem 1.5rem;
  border-bottom: 1px solid #e5e7eb;
}

.card-header h3 {
  font-size: 1rem;
  font-weight: 600;
  color: #1e293b;
  margin: 0;
}

.empty-state {
  padding: 2rem;
  text-align: center;
  color: #94a3b8;
}

.recent-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.875rem;
}

.recent-table th,
.recent-table td {
  padding: 0.65rem 1rem;
  text-align: left;
  border-bottom: 1px solid #f1f5f9;
}

.recent-table th {
  background: #f8fafc;
  font-weight: 600;
  color: #64748b;
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.recent-table tbody tr:hover {
  background: #f8fafc;
}

.recent-table tbody tr:last-child td {
  border-bottom: none;
}

.cell-id {
  color: #94a3b8;
  font-size: 0.75rem;
}

.cell-filename {
  max-width: 200px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-weight: 500;
  color: #1e293b;
}

.cell-success {
  color: #16a34a;
  font-weight: 600;
}

.cell-failed {
  color: #dc2626;
  font-weight: 600;
}

.cell-date {
  color: #64748b;
  white-space: nowrap;
  font-size: 0.8rem;
}

/* Type badge */
.type-badge {
  display: inline-block;
  padding: 0.1rem 0.5rem;
  border-radius: 4px;
  font-size: 0.72rem;
  font-weight: 600;
  text-transform: uppercase;
  background: #e0e7ff;
  color: #3730a3;
}

.type-sheet {
  background: #fef3c7;
  color: #92400e;
}

/* Status badge */
.status-badge {
  display: inline-block;
  padding: 0.1rem 0.5rem;
  border-radius: 4px;
  font-size: 0.72rem;
  font-weight: 600;
  text-transform: capitalize;
  background: #f1f5f9;
  color: #64748b;
}

.status-success {
  background: #dcfce7;
  color: #16a34a;
}

.status-failed {
  background: #fee2e2;
  color: #dc2626;
}

.status-pending,
.status-processing {
  background: #fef9c3;
  color: #854d0e;
}
</style>
