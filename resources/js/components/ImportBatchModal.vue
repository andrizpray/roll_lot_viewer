<template>
  <div class="modal-overlay" @click.self="$emit('close')">
    <div class="modal-content">
      <div class="modal-header">
        <h3>Import Batch Detail</h3>
        <button @click="$emit('close')" class="close-btn" aria-label="Close detail modal">
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
            <label>Type:</label>
            <span>{{ batch.type === 'sheet' ? 'Mutasi Stock Sheet' : 'Mutasi Roll' }}</span>
          </div>
          <div class="detail-item">
            <label>Status:</label>
            <span>{{ batch.status }}</span>
          </div>
          <div class="detail-item">
            <label>Total Rows:</label>
            <span>{{ batch.total_rows ?? '-' }}</span>
          </div>
          <div class="detail-item">
            <label>Success:</label>
            <span>{{ batch.success_count ?? '-' }}</span>
          </div>
          <div class="detail-item">
            <label>Failed:</label>
            <span>{{ batch.failed_count ?? '-' }}</span>
          </div>
        </div>

        <div v-if="batch.errors && batch.errors.length > 0" class="errors-section">
          <h4>Baris Gagal ({{ batch.errors.length }})</h4>
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
                <td>{{ err.lot_id || '-' }}</td>
                <td>{{ err.reason }}</td>
              </tr>
            </tbody>
          </table>
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
  margin-bottom: 1.5rem;
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

.detail-item label {
  font-size: 0.875rem;
  color: #64748b;
  font-weight: 500;
}

.detail-item span {
  font-size: 1rem;
  color: #1e293b;
}

.errors-section {
  margin-top: 2rem;
  border-top: 1px solid #e5e7eb;
  padding-top: 1.5rem;
}

.errors-section h4 {
  font-size: 1rem;
  color: #991b1b;
  margin-bottom: 0.75rem;
}

.errors-table {
  width: 100%;
  border-collapse: collapse;
}

.errors-table th {
  text-align: left;
  padding: 0.5rem;
  font-weight: 600;
  background: #fef2f2;
  border-bottom: 1px solid #fee2e2;
}

.errors-table td {
  padding: 0.5rem;
  border-bottom: 1px solid #f1f5f9;
}
</style>
