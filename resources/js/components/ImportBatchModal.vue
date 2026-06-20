<template>
  <div class="modal-overlay" @click.self="$emit('close')">
    <div class="modal-content">
      <div class="modal-header">
        <div class="modal-title-row">
          <svg class="modal-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
            <polyline points="14 2 14 8 20 8"/>
            <line x1="16" y1="13" x2="8" y2="13"/>
            <line x1="16" y1="17" x2="8" y2="17"/>
          </svg>
          <h3>Import Batch Detail</h3>
        </div>
        <button @click="$emit('close')" class="close-btn" aria-label="Close">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
            <line x1="18" y1="6" x2="6" y2="18"/>
            <line x1="6" y1="6" x2="18" y2="18"/>
          </svg>
        </button>
      </div>
      <div class="modal-body">
        <div class="detail-grid">
          <div class="detail-item full-width">
            <label>Filename</label>
            <span class="val-file">{{ batch.filename }}</span>
          </div>
          <div class="detail-item">
            <label>Type</label>
            <span>
              <span :class="['type-pill', 'type-pill-' + batch.type]">
                {{ batch.type === 'sheet' ? 'Mutasi Stock Sheet' : 'Mutasi Roll' }}
              </span>
            </span>
          </div>
          <div class="detail-item">
            <label>Status</label>
            <span>
              <span :class="['status-pill', 'pill-' + batch.status]">{{ batch.status }}</span>
            </span>
          </div>
          <div class="detail-item">
            <label>Total Rows</label>
            <span>{{ batch.total_rows ?? '-' }}</span>
          </div>
          <div class="detail-item">
            <label>Success</label>
            <span class="val-ok">{{ batch.success_count ?? '-' }}</span>
          </div>
          <div class="detail-item">
            <label>Failed</label>
            <span class="val-fail">{{ batch.failed_count ?? '-' }}</span>
          </div>
        </div>

        <div v-if="batch.errors && batch.errors.length > 0" class="errors-section">
          <h4>
            <svg class="err-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
              <circle cx="12" cy="12" r="10"/>
              <line x1="15" y1="9" x2="9" y2="15"/>
              <line x1="9" y1="9" x2="15" y2="15"/>
            </svg>
            Baris Gagal ({{ batch.errors.length }})
          </h4>
          <div class="table-wrapper">
          <table class="errors-table">
            <thead>
              <tr>
                <th>Row</th>
                <th>LotID</th>
                <th>Reason</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="err in batch.errors" :key="err.id">
                <td>{{ err.row_number }}</td>
                <td class="cell-mono">{{ err.lot_id || '-' }}</td>
                <td>{{ err.reason }}</td>
              </tr>
            </tbody>
          </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  batch: {
    type: Object,
    required: true,
  },
  history: {
    type: Array,
    default: () => [],
  },
});

defineEmits(['close']);
</script>

<style scoped>
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(15, 23, 42, 0.7);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  animation: fadeIn 0.15s ease;
  padding: 1rem;
}
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

.modal-content {
  background: white;
  border-radius: 1rem;
  max-width: 700px;
  width: 100%;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4);
  animation: slideUp 0.2s ease;
}
@keyframes slideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.5rem 2rem;
  border-bottom: 1px solid #e2e8f0;
  background: #f8fafc;
  border-radius: 1rem 1rem 0 0;
}
.modal-title-row { display: flex; align-items: center; gap: 0.75rem; }
.modal-icon { width: 1.5rem; height: 1.5rem; color: #059669; }
.modal-header h3 { font-size: 1.3rem; font-weight: 700; color: #0f172a; margin: 0; }

.close-btn {
  width: 2.25rem; height: 2.25rem;
  display: flex; align-items: center; justify-content: center;
  border: none; background: transparent; color: #94a3b8;
  cursor: pointer; border-radius: 0.5rem; transition: all 0.2s;
}
.close-btn:hover { background: #e2e8f0; color: #1e293b; }

.modal-body { padding: 1.75rem 2rem; }
.detail-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1.25rem;
}
.detail-item { display: flex; flex-direction: column; gap: 0.25rem; }
.detail-item.full-width { grid-column: 1 / -1; }
.detail-item label {
  font-size: 0.75rem; color: #94a3b8; font-weight: 600;
  text-transform: uppercase; letter-spacing: 0.04em;
}
.detail-item span { font-size: 1rem; color: #1e293b; font-weight: 500; }
.val-file { font-weight: 600; font-size: 0.95rem; word-break: break-all; }
.val-ok { color: #16a34a !important; font-weight: 700; }
.val-fail { color: #dc2626 !important; font-weight: 700; }

.type-pill, .status-pill {
  display: inline-block;
  padding: 0.2rem 0.6rem;
  border-radius: 999px;
  font-size: 0.78rem;
  font-weight: 600;
  text-transform: capitalize;
}
.type-pill-roll { background: #e0f2fe; color: #075985; }
.type-pill-sheet { background: #fae8ff; color: #86198f; }
.pill-success { background: #dcfce7; color: #166534; }
.pill-failed { background: #fee2e2; color: #991b1b; }
.pill-pending, .pill-processing { background: #fef3c7; color: #92400e; }

/* ─── Errors Section ─── */
.errors-section {
  margin-top: 2rem;
  border-top: 1px solid #e2e8f0;
  padding-top: 1.5rem;
}
.errors-section h4 {
  font-size: 1rem;
  font-weight: 600;
  color: #dc2626;
  margin-bottom: 1rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}
.err-icon { flex-shrink: 0; }

.table-wrapper { overflow-x: auto; border-radius: 0.5rem; border: 1px solid #fee2e2; }
.errors-table { width: 100%; border-collapse: separate; border-spacing: 0; }
.errors-table th {
  text-align: left;
  padding: 0.625rem 0.75rem;
  font-weight: 600;
  font-size: 0.8rem;
  background: #fef2f2;
  border-bottom: 1px solid #fee2e2;
  color: #991b1b;
  text-transform: uppercase;
  letter-spacing: 0.03em;
}
.errors-table td {
  padding: 0.625rem 0.75rem;
  border-bottom: 1px solid #f1f5f9;
  font-size: 0.85rem;
}
.errors-table tbody tr:nth-child(even) { background: #fafafa; }
.errors-table tbody tr:last-child td { border-bottom: none; }
.cell-mono { font-family: 'SF Mono', 'Fira Code', monospace; font-weight: 600; }
</style>
