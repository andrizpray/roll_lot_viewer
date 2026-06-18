<template>
  <div class="modal-overlay" @click.self="$emit('close')">
    <div class="modal-content">
      <div class="modal-header">
        <h3>Roll Lot Detail</h3>
        <button @click="$emit('close')" class="close-btn" aria-label="Close detail modal">
          <i class="pi pi-times"></i>
        </button>
      </div>
      <div class="modal-body">
        <div class="detail-grid">
          <div class="detail-item">
            <label>Lot ID:</label>
            <span>{{ lot.lot_id }}</span>
          </div>
          <div class="detail-item">
            <label>Item ID:</label>
            <span>{{ lot.item_id }}</span>
          </div>
          <div class="detail-item">
            <label>Weight:</label>
            <span>{{ lot.weight }}</span>
          </div>
          <div class="detail-item">
            <label>Papertype:</label>
            <span>{{ lot.papertype }}</span>
          </div>
          <div class="detail-item">
            <label>Gramature:</label>
            <span>{{ lot.gramature }}</span>
          </div>
          <div class="detail-item">
            <label>Playbond:</label>
            <span>{{ lot.playbond || '-' }}</span>
          </div>
          <div class="detail-item">
            <label>Width:</label>
            <span>{{ lot.width }}</span>
          </div>
          <div class="detail-item">
            <label>Rew ID:</label>
            <span>{{ lot.rew_id || '-' }}</span>
          </div>
          <div class="detail-item">
            <label>Grade:</label>
            <span>{{ lot.grade || '-' }}</span>
          </div>
          <div class="detail-item">
            <label>Diameter:</label>
            <span>{{ lot.diameter || '-' }}</span>
          </div>
          <div class="detail-item">
            <label>Thickness:</label>
            <span>{{ lot.thickness || '-' }}</span>
          </div>
          <div class="detail-item">
            <label>Transaction Date:</label>
            <span>{{ lot.source_tr_date || '-' }}</span>
          </div>
          <div class="detail-item">
            <label>Transaction Time:</label>
            <span>{{ lot.source_tr_time || '-' }}</span>
          </div>
          <div class="detail-item full-width">
            <label>Description:</label>
            <span>{{ lot.description_raw }}</span>
          </div>
          <div class="detail-item full-width">
            <label>Comments:</label>
            <span>{{ lot.comments || '-' }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  lot: {
    type: Object,
    required: true,
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
</style>
