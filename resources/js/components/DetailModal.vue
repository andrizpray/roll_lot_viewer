<template>
  <div class="modal-overlay" @click.self="$emit('close')">
    <div class="modal-content">
      <div class="modal-header">
        <div class="modal-title-row">
          <svg class="modal-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <ellipse cx="12" cy="7" rx="8" ry="4"/>
            <path d="M20 7v4c0 2.2-3.6 4-8 4s-8-1.8-8-4V7"/>
            <path d="M4 11v4c0 2.2 3.6 4 8 4s8-1.8 8-4v-4"/>
          </svg>
          <h3>Lot Detail</h3>
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
          <div class="detail-item">
            <label>Lot ID</label>
            <span class="val-lotid">{{ lot.lot_id }}</span>
          </div>
          <div class="detail-item">
            <label>Item ID</label>
            <span>{{ lot.item_id }}</span>
          </div>
          <div class="detail-item">
            <label>Weight</label>
            <span class="val-weight">{{ lot.weight }} kg</span>
          </div>
          <div class="detail-item">
            <label>Rew ID</label>
            <span class="val-muted">{{ lot.rew_id || '-' }}</span>
          </div>
          <div class="detail-item">
            <label>Paper Type</label>
            <span>{{ lot.papertype }}</span>
          </div>
          <div class="detail-item">
            <label>Gramature</label>
            <span>{{ lot.gramature }}</span>
          </div>
          <div class="detail-item">
            <label>Play Bond</label>
            <span>{{ lot.playbond }}</span>
          </div>
          <div class="detail-item">
            <label>Width</label>
            <span>{{ lot.width }}</span>
          </div>
          <div class="detail-item">
            <label>Grade</label>
            <span>
              <template v-if="lot.grade">
                <span :class="['grade-tag', 'g-' + lot.grade]">{{ lot.grade }}</span>
              </template>
              <span v-else class="val-muted">-</span>
            </span>
          </div>
          <div class="detail-item">
            <label>Diameter</label>
            <span>{{ lot.diameter ? lot.diameter + 'mm' : '-' }}</span>
          </div>
          <div class="detail-item">
            <label>Transaction Date</label>
            <span>{{ lot.source_tr_date || '-' }}</span>
          </div>
          <div class="detail-item">
            <label>Transaction Time</label>
            <span>{{ lot.source_tr_time || '-' }}</span>
          </div>
          <div class="detail-item full-width">
            <label>Description</label>
            <span class="val-desc">{{ lot.description_raw || '-' }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, onUnmounted } from 'vue';

defineProps({
  lot: {
    type: Object,
    required: true,
  },
});

const emit = defineEmits(['close']);

const handleEsc = (e) => {
  if (e.key === 'Escape') emit('close');
};

onMounted(() => window.addEventListener('keydown', handleEsc));
onUnmounted(() => window.removeEventListener('keydown', handleEsc));
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
.modal-title-row {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}
.modal-icon { width: 1.5rem; height: 1.5rem; color: #059669; }
.modal-header h3 { font-size: 1.3rem; font-weight: 700; color: #0f172a; margin: 0; }

.close-btn {
  width: 2.25rem;
  height: 2.25rem;
  display: flex;
  align-items: center;
  justify-content: center;
  border: none;
  background: transparent;
  color: #94a3b8;
  cursor: pointer;
  border-radius: 0.5rem;
  transition: all 0.2s;
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
  font-size: 0.75rem;
  color: #94a3b8;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.detail-item span { font-size: 1rem; color: #1e293b; font-weight: 500; }

.val-lotid { font-weight: 700; font-family: 'SF Mono', 'Fira Code', monospace; color: #0f172a; font-size: 1.1rem; }
.val-weight { font-weight: 700; color: #059669; }
.val-muted { color: #94a3b8 !important; }
.val-desc { color: #64748b; line-height: 1.5; }

/* Grade tags */
.grade-tag {
  display: inline-block;
  padding: 0.15rem 0.6rem;
  border-radius: 4px;
  font-weight: 700;
  font-size: 0.85rem;
}
.g-1 { background: #fef2f2; color: #dc2626; }
.g-2 { background: #fef3c7; color: #d97706; }
.g-3 { background: #f0fdf4; color: #16a34a; }
</style>
