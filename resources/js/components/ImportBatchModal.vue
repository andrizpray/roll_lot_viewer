<template>
  <div class="modal-overlay" @click.self="$emit('close')">
    <div class="modal-content">
      <div class="modal-header">
        <h3>Import Batch Detail</h3>
        <button @click="$emit('close')" class="close-btn" aria-label="Close import detail modal">
          <i class="pi pi-times"></i>
        </button>
      </div>
      <div class="modal-body">
        <div class="detail-grid">
          <div class="detail-item">
            <label>Filename:</label>
            <span>{{ batch.filename }}</span>
          </div>
          <div class="detail-item">
            <label>Status:</label>
            <span :class="'status-badge status-' + batch.status">{{ batch.status }}</span>
          </div>
          <div class="detail-item">
            <label>Total Rows:</label>
            <span>{{ batch.total_rows || 0 }}</span>
          </div>
          <div class="detail-item">
            <label>Success:</label>
            <span class="text-success">{{ batch.success_count || 0 }}</span>
          </div>
          <div class="detail-item">
            <label>Failed:</label>
            <span class="text-danger">{{ batch.failed_count || 0 }}</span>
          </div>
          <div class="detail-item full-width">
            <label>Created At:</label>
            <span>{{ formatDate(batch.created_at) }}</span>
          </div>
        </div>

        <!-- Error List -->
        <div v-if="errors.length > 0" class="errors-section">
          <h4>Errors ({{ errors.length }})</h4>
          <div class="error-table-wrapper">
            <table class="error-table">
              <thead>
                <tr>
                  <th>Row</th>
                  <th>LotID</th>
                  <th>Reason</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="err in errors" :key="err.id">
                  <td>{{ err.row_number || '-' }}</td>
                  <td>{{ err.lot_id || '-' }}</td>
                  <td>{{ err.reason }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div v-else-if="batch.status === 'success'" class="no-errors">
          <i class="pi pi-check-circle"></i>
          <span>No errors in this import.</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  batch: {
    type: Object,
    required: true,
  },
  errors: {
    type: Array,
    default: () => [],
  },
});

defineEmits(['close']);

const formatDate = (dateString) => {
  if (!dateString) return '-';
  return new Date(dateString).toLocaleDateString('id-ID', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};
</script>

<style scoped>
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.modal-content {
  background: white;
  border-radius: 0.75rem;
  max-width: 700px;
  width: 90%;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.5rem;
  border-bottom: 1px solid #e5e7eb;
}

.modal-header h3 {
  font-size: 1.25rem;
  color: #1e293b;
}

.close-btn {
  background: none;
  border: none;
  font-size: 1.25rem;
  color: #64748b;
  cursor: pointer;
  padding: 0.5rem;
  border-radius: 0.375rem;
  transition: all 0.2s;
}

.close-btn:hover {
  background: #f1f5f9;
  color: #1e293b;
}

.modal-body {
  padding: 1.5rem;
}

.detail-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1.5rem;
}

.detail-item {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.detail-item.full-width {
  grid-column: 1 / -1;
}

.detail-item label {
  font-size: 0.875rem;
  color: #64748b;
  font-weight: 500;
}

.detail-item span {
  font-size: 1rem;
  color: #1e293b;
}

.text-success { color: #166534; }
.text-danger { color: #dc2626; }

.status-badge {
  display: inline-block;
  padding: 0.25rem 0.75rem;
  border-radius: 0.375rem;
  font-size: 0.875rem;
  font-weight: 500;
  width: fit-content;
}

.status-processing { background: #dbeafe; color: #1e40af; }
.status-success { background: #dcfce7; color: #166534; }
.status-failed { background: #fee2e2; color: #991b1b; }

.errors-section {
  margin-top: 2rem;
  border-top: 1px solid #e5e7eb;
  padding-top: 1.5rem;
}

.errors-section h4 {
  font-size: 1rem;
  color: #991b1b;
  margin-bottom: 1rem;
}

.error-table-wrapper {
  overflow-x: auto;
}

.error-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.875rem;
}

.error-table th {
  background: #f8fafc;
  padding: 0.75rem;
  text-align: left;
  font-weight: 600;
  color: #475569;
  border-bottom: 2px solid #e5e7eb;
}

.error-table td {
  padding: 0.75rem;
  border-bottom: 1px solid #e5e7eb;
  color: #475569;
}

.no-errors {
  margin-top: 1.5rem;
  padding: 1rem;
  background: #dcfce7;
  border-radius: 0.5rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: #166534;
}

.no-errors i {
  font-size: 1.25rem;
}
</style>

