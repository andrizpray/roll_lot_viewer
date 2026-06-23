<template>
  <div class="upload-page">
    <div class="header">
      <h2>
        <svg class="page-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
          <polyline points="17 8 12 3 7 8"/>
          <line x1="12" y1="3" x2="12" y2="15"/>
        </svg>
        Upload & Import
      </h2>
    </div>

    <!-- Notification Banner -->
    <div v-if="notification.show" :class="['notification', 'notif-' + notification.type]">
      <svg class="notif-icon" v-if="notification.type === 'success'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
      </svg>
      <svg class="notif-icon" v-else-if="notification.type === 'failed'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>
      </svg>
      <svg class="notif-icon" v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
      </svg>
      <div class="notif-body">
        <strong>{{ notification.title }}</strong>
        <span v-if="notification.message">{{ notification.message }}</span>
      </div>
      <button v-if="notification.dismissible" @click="dismissNotification" class="notif-close">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
          <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
      </button>
    </div>

    <div class="upload-section">
      <div
        class="dropzone"
        :class="{
          'dropzone-active': isDragging,
          'dropzone-disabled': uploadStatus === 'uploading' || uploadStatus === 'processing'
        }"
        @dragover.prevent="onDragOver"
        @dragleave.prevent="onDragLeave"
        @drop.prevent="handleDrop"
      >
        <div class="dropzone-content">
          <svg class="dropzone-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
            <polyline points="17 8 12 3 7 8"/>
            <line x1="12" y1="3" x2="12" y2="15"/>
          </svg>
          <p class="dropzone-text">Drag & drop Excel file here</p>
          <p class="dropzone-hint">.xlsx or .xls</p>
          <label for="file-upload" class="btn btn-primary" :class="{ 'btn-loading': uploadStatus === 'uploading' }">
            <svg v-if="uploadStatus === 'uploading'" class="spin-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="12" y1="2" x2="12" y2="6"/><line x1="12" y1="18" x2="12" y2="22"/>
              <line x1="4.93" y1="4.93" x2="7.76" y2="7.76"/><line x1="16.24" y1="16.24" x2="19.07" y2="19.07"/>
              <line x1="2" y1="12" x2="6" y2="12"/><line x1="18" y1="12" x2="22" y2="12"/>
              <line x1="4.93" y1="19.07" x2="7.76" y2="16.24"/><line x1="16.24" y1="7.76" x2="19.07" y2="4.93"/>
            </svg>
            <svg v-else class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
              <polyline points="14 2 14 8 20 8"/>
            </svg>
            {{ uploadStatus === 'uploading' ? 'Uploading...' : 'Browse Files' }}
          </label>
          <input
            id="file-upload"
            type="file"
            accept=".xlsx,.xls"
            @change="handleFileSelect"
            hidden
            :disabled="uploadStatus === 'uploading' || uploadStatus === 'processing'"
          />
        </div>
      </div>

      <!-- Progress indicator -->
      <div v-if="uploadProgress > 0 || uploadStatus === 'processing' || uploadStatus === 'success' || uploadStatus === 'failed'" class="progress-section">
        <div class="progress-header">
          <span class="progress-label">
            <svg v-if="uploadStatus === 'processing'" class="spin-icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="12" y1="2" x2="12" y2="6"/><line x1="12" y1="18" x2="12" y2="22"/>
              <line x1="4.93" y1="4.93" x2="7.76" y2="7.76"/><line x1="16.24" y1="16.24" x2="19.07" y2="19.07"/>
              <line x1="2" y1="12" x2="6" y2="12"/><line x1="18" y1="12" x2="22" y2="12"/>
            </svg>
            <svg v-else-if="uploadStatus === 'success'" class="done-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
            </svg>
            <svg v-else-if="uploadStatus === 'failed'" class="fail-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>
            </svg>
            {{ progressText }}
          </span>
          <span class="progress-pct" :class="{ 'pct-done': uploadStatus === 'success', 'pct-fail': uploadStatus === 'failed' }">
            {{ uploadProgress }}%
          </span>
        </div>
        <div class="progress-track">
          <div
            class="progress-fill"
            :style="{ width: uploadProgress + '%' }"
            :class="{
              'progress-done': uploadStatus === 'success',
              'progress-fail': uploadStatus === 'failed',
              'progress-indeterminate': uploadStatus === 'processing' && uploadProgress === 100
            }"
          ></div>
        </div>

        <!-- Result details when done -->
        <div v-if="uploadResult" class="result-details">
          <div class="result-stat">
            <span class="stat-label">Total</span>
            <span class="stat-val">{{ uploadResult.total_rows }}</span>
          </div>
          <div class="result-stat ok">
            <span class="stat-label">Berhasil</span>
            <span class="stat-val">{{ uploadResult.success_count }}</span>
          </div>
          <div class="result-stat fail" v-if="uploadResult.failed_count > 0">
            <span class="stat-label">Gagal</span>
            <span class="stat-val">{{ uploadResult.failed_count }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Import History -->
    <div class="history-section">
      <h3>
        <svg class="inline-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
          <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
          <polyline points="14 2 14 8 20 8"/>
        </svg>
        Import History
      </h3>
      <div class="table-wrapper">
      <table class="history-table">
        <thead>
          <tr>
            <th>File</th>
            <th>Type</th>
            <th>Status</th>
            <th>Total Rows</th>
            <th>Success</th>
            <th>Failed</th>
            <th>Date</th>
            <th class="col-action">Action</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="batch in importHistory" :key="batch.id">
            <td class="filename">{{ batch.filename }}</td>
            <td>
              <span :class="['type-pill', 'type-pill-' + batch.type]">
                {{ batch.type === 'sheet' ? 'Sheet' : 'Roll' }}
              </span>
            </td>
            <td>
              <span :class="['status-pill', 'pill-' + batch.status]">{{ batch.status }}</span>
            </td>
            <td>{{ batch.total_rows ?? '-' }}</td>
            <td class="cell-ok">{{ batch.success_count ?? '-' }}</td>
            <td class="cell-fail">{{ batch.failed_count ?? '-' }}</td>
            <td class="cell-date">{{ batch.created_at }}</td>
            <td class="col-action">
              <button @click="viewDetails(batch)" class="btn btn-sm btn-outline">Details</button>
            </td>
          </tr>
        </tbody>
      </table>
      </div>
    </div>

    <ImportBatchModal
      v-if="selectedImport"
      :batch="selectedImport"
      :history="importHistory"
      @close="selectedImport = null"
    />
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { onUnmounted } from 'vue';
import axios from 'axios';
import ImportBatchModal from '../components/ImportBatchModal.vue';

// Poll cancellation flag
let pollCancelled = false;

onUnmounted(() => {
  pollCancelled = true;
});

const selectedImport = ref(null);
const importHistory = ref([]);
const uploadProgress = ref(0);
const progressText = ref('');
const isDragging = ref(false);
const uploadStatus = ref(''); // '' | 'uploading' | 'processing' | 'success' | 'failed'
const uploadResult = ref(null);
const notification = ref({ show: false, type: '', title: '', message: '', dismissible: true });

function dismissNotification() {
  notification.value.show = false;
}

function showNotification(type, title, message) {
  notification.value = { show: true, type, title, message: message || '', dismissible: true };
}

function onDragOver() {
  if (uploadStatus.value !== 'uploading' && uploadStatus.value !== 'processing') {
    isDragging.value = true;
  }
}
function onDragLeave() {
  isDragging.value = false;
}
function clearUpload() {
  uploadProgress.value = 0;
  progressText.value = '';
  uploadStatus.value = '';
  uploadResult.value = null;
}

const loadHistory = async () => {
  try {
    const response = await axios.get('/api/imports');
    importHistory.value = response.data.data || [];
  } catch (error) {
    console.error('Failed to load import history:', error);
  }
};

const uploadFile = async (file) => {
  // Validate file type
  const name = file.name.toLowerCase();
  if (!name.endsWith('.xlsx') && !name.endsWith('.xls')) {
    showNotification('failed', 'Format file tidak didukung', 'Gunakan file .xlsx atau .xls');
    return;
  }

  clearUpload();
  const formData = new FormData();
  formData.append('file', file);

  uploadStatus.value = 'uploading';
  uploadProgress.value = 1;
  progressText.value = 'Uploading to server...';

  try {
    const response = await axios.post('/api/imports', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
      onUploadProgress: (progressEvent) => {
        if (progressEvent.total) {
          uploadProgress.value = Math.round((progressEvent.loaded * 100) / progressEvent.total);
        }
      },
    });

    const jobId = response.data.job_id;
    uploadStatus.value = 'processing';
    progressText.value = 'Processing file...';

    // Poll for completion
    let retryCount = 0;
    const checkStatus = async () => {
      if (pollCancelled) return;
      try {
        const statusRes = await axios.get(`/api/imports/${jobId}/status`);
        const status = statusRes.data.status;

        if (status === 'completed') {
          uploadProgress.value = 100;
          uploadStatus.value = 'success';
          uploadResult.value = {
            total_rows: statusRes.data.total_rows,
            success_count: statusRes.data.success_count,
            failed_count: statusRes.data.failed_count,
          };
          progressText.value = `Import selesai! ${statusRes.data.success_count} berhasil`;
          if (statusRes.data.failed_count > 0) {
            progressText.value += `, ${statusRes.data.failed_count} gagal`;
          }
          showNotification('success', 'Import berhasil', `${statusRes.data.success_count} row imported`);
          retryCount = 0;
          await loadHistory();
        } else if (status === 'failed') {
          uploadStatus.value = 'failed';
          progressText.value = 'Import gagal. Periksa log untuk detail.';
          showNotification('failed', 'Import gagal', statusRes.data.message || 'Lihat detail untuk informasi lebih lanjut');
          await loadHistory();
        } else {
          throw new Error('Still processing');
        }
      } catch (error) {
        if (retryCount < 30 && !pollCancelled) {
          retryCount++;
          setTimeout(checkStatus, 2000);
        } else {
          uploadStatus.value = 'failed';
          progressText.value = 'Gagal memantau status import. Coba refresh halaman.';
          showNotification('failed', 'Monitoring timeout', 'Refresh halaman untuk melihat status import');
        }
      }
    };

    checkStatus();
  } catch (error) {
    uploadStatus.value = 'failed';
    uploadProgress.value = 0;
    const errMsg = error.response?.data?.message || error.message;
    progressText.value = 'Upload gagal: ' + errMsg;
    showNotification('failed', 'Upload gagal', errMsg);
  }
};

const handleDrop = (event) => {
  isDragging.value = false;
  const file = event.dataTransfer.files[0];
  if (file) uploadFile(file);
};

const handleFileSelect = (event) => {
  const file = event.target.files[0];
  if (file) uploadFile(file);
  // Reset input so same file can be re-selected
  event.target.value = '';
};

const viewDetails = async (batch) => {
  try {
    const response = await axios.get(`/api/imports/${batch.id}`);
    selectedImport.value = response.data;
  } catch (error) {
    console.error('Failed to load import details:', error);
  }
};

onMounted(() => {
  loadHistory();
});
</script>

<style scoped>
/* ─── Page ─── */
.upload-page { padding: 0; }
.header { margin-bottom: 1.5rem; }
.header h2 {
  font-size: 1.75rem;
  font-weight: 700;
  color: #1e293b;
  display: flex;
  align-items: center;
  gap: 0.625rem;
}
.page-icon { width: 1.75rem; height: 1.75rem; color: #059669; }

/* ─── Notification Banner ─── */
.notification {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  padding: 0.875rem 1.25rem;
  border-radius: 0.75rem;
  margin-bottom: 1rem;
  border: 1px solid;
  animation: slideDown 0.25s ease;
}
@keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
.notif-icon { width: 1.25rem; height: 1.25rem; flex-shrink: 0; margin-top: 1px; }
.notif-body { display: flex; flex-direction: column; gap: 0.15rem; flex: 1; font-size: 0.9rem; }
.notif-body strong { font-size: 0.95rem; }
.notif-close {
  background: none; border: none; cursor: pointer; padding: 0.25rem;
  color: inherit; opacity: 0.6; border-radius: 0.25rem; flex-shrink: 0;
}
.notif-close:hover { opacity: 1; }
.notif-success { background: #f0fdf4; border-color: #bbf7d0; color: #166534; }
.notif-failed { background: #fef2f2; border-color: #fecaca; color: #991b1b; }
.notif-error { background: #fef2f2; border-color: #fecaca; color: #991b1b; }

/* ─── Upload Section ─── */
.upload-section {
  background: white;
  padding: 2rem;
  border-radius: 0.75rem;
  margin-bottom: 2rem;
  border: 1px solid #e2e8f0;
}

/* ─── Dropzone ─── */
.dropzone {
  border: 2px dashed #d1d5db;
  border-radius: 1rem;
  padding: 3rem 2rem;
  text-align: center;
  transition: all 0.3s ease;
  cursor: pointer;
  background: #f8fafc;
}
.dropzone:hover:not(.dropzone-disabled) {
  border-color: #059669;
  background: #ecfdf5;
}
.dropzone-active {
  border-color: #059669 !important;
  background: #ecfdf5 !important;
  transform: scale(1.02);
  box-shadow: 0 0 0 4px rgba(5, 150, 105, 0.15);
}
.dropzone-disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
.dropzone-content {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.75rem;
}
.dropzone-svg {
  width: 4rem;
  height: 4rem;
  color: #94a3b8;
  transition: all 0.3s ease;
}
.dropzone-active .dropzone-svg { color: #059669; transform: translateY(-4px); }
.dropzone-text { font-size: 1.1rem; font-weight: 600; color: #475569; margin: 0; }
.dropzone-hint { font-size: 0.85rem; color: #94a3b8; margin: 0 0 0.5rem 0; }

/* ─── Buttons ─── */
.btn {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem 1.5rem;
  border-radius: 0.5rem;
  font-weight: 600;
  cursor: pointer;
  border: none;
  transition: all 0.2s ease;
  font-size: 0.95rem;
}
.btn-primary { background: #059669; color: white; }
.btn-primary:hover { background: #047857; box-shadow: 0 2px 8px rgba(5, 150, 105, 0.3); }
.btn-loading { background: #047857; cursor: wait; }
.btn-outline {
  background: transparent;
  color: #059669;
  border: 1.5px solid #059669;
}
.btn-outline:hover { background: #ecfdf5; }
.btn-sm { padding: 0.4rem 0.75rem; font-size: 0.8rem; }
.btn-icon { width: 1.2rem; height: 1.2rem; }
.inline-icon { width: 1rem; height: 1rem; margin-right: 0.3rem; vertical-align: middle; }

/* ─── Spinner ─── */
.spin-icon { width: 1.2rem; height: 1.2rem; animation: spin 1s linear infinite; }
.spin-icon-sm { width: 1rem; height: 1rem; animation: spin 1s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

.done-icon { width: 1rem; height: 1rem; color: #16a34a; }
.fail-icon { width: 1rem; height: 1rem; color: #dc2626; }

/* ─── Progress ─── */
.progress-section {
  margin-top: 1.5rem;
  padding: 1.25rem;
  background: #f8fafc;
  border-radius: 0.75rem;
  border: 1px solid #e2e8f0;
}
.progress-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.75rem;
}
.progress-label {
  font-size: 0.9rem;
  font-weight: 500;
  color: #475569;
  display: flex;
  align-items: center;
  gap: 0.4rem;
}
.progress-pct { font-size: 0.85rem; font-weight: 700; color: #059669; }
.pct-done { color: #16a34a; }
.pct-fail { color: #dc2626; }
.progress-track {
  height: 0.625rem;
  background: #e2e8f0;
  border-radius: 999px;
  overflow: hidden;
}
.progress-fill {
  height: 100%;
  background: linear-gradient(90deg, #059669, #10b981);
  border-radius: 999px;
  transition: width 0.5s ease;
}
.progress-done { background: linear-gradient(90deg, #16a34a, #22c55e); }
.progress-fail { background: linear-gradient(90deg, #ef4444, #f87171); }
.progress-indeterminate {
  background: linear-gradient(90deg, #059669, #34d399, #059669);
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
}
@keyframes shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }

/* ─── Result Details ─── */
.result-details {
  display: flex;
  gap: 1.5rem;
  margin-top: 1rem;
  padding-top: 1rem;
  border-top: 1px solid #e2e8f0;
}
.result-stat { display: flex; flex-direction: column; align-items: center; gap: 0.15rem; }
.stat-label { font-size: 0.7rem; text-transform: uppercase; color: #94a3b8; font-weight: 600; letter-spacing: 0.04em; }
.stat-val { font-size: 1.3rem; font-weight: 700; color: #1e293b; }
.result-stat.ok .stat-val { color: #16a34a; }
.result-stat.fail .stat-val { color: #dc2626; }

/* ─── History Section ─── */
.history-section {
  background: white;
  padding: 1.5rem 2rem;
  border-radius: 0.75rem;
  border: 1px solid #e2e8f0;
}
.history-section h3 {
  font-size: 1.15rem;
  font-weight: 600;
  color: #1e293b;
  margin-bottom: 1.25rem;
  display: flex;
  align-items: center;
  gap: 0.4rem;
}
.table-wrapper { overflow-x: auto; -webkit-overflow-scrolling: touch; border-radius: 0.5rem; border: 1px solid #f1f5f9; }
.history-table { width: 100%; border-collapse: separate; border-spacing: 0; min-width: 700px; }
.history-table thead { position: sticky; top: 0; z-index: 10; }
.history-table th {
  padding: 0.875rem 1rem;
  text-align: left;
  font-weight: 600;
  color: #475569;
  background: #f8fafc;
  border-bottom: 2px solid #e2e8f0;
  font-size: 0.8rem;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  white-space: nowrap;
}
.history-table td { padding: 0.75rem 1rem; border-bottom: 1px solid #f1f5f9; font-size: 0.9rem; }
.history-table tbody tr:nth-child(even) { background: #f8fafc; }
.history-table tbody tr:hover { background: #ecfdf5; }
.history-table tbody tr:last-child td { border-bottom: none; }
.col-action { width: 80px; text-align: center; }
.filename { max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-weight: 500; }
.cell-ok { color: #16a34a; font-weight: 600; }
.cell-fail { color: #dc2626; font-weight: 600; }
.cell-date { color: #64748b; white-space: nowrap; font-size: 0.85rem; }

/* ─── Badges / Pills ─── */
.status-pill, .type-pill {
  display: inline-block;
  padding: 0.2rem 0.6rem;
  border-radius: 999px;
  font-size: 0.78rem;
  font-weight: 600;
  text-transform: capitalize;
}
.pill-success { background: #dcfce7; color: #166534; }
.pill-failed { background: #fee2e2; color: #991b1b; }
.pill-pending, .pill-processing { background: #fef3c7; color: #92400e; }
.type-pill-roll { background: #e0f2fe; color: #075985; }
.type-pill-sheet { background: #fae8ff; color: #86198f; }

/* ─── Focus ─── */
.btn:focus-visible { outline: 2px solid #059669; outline-offset: 2px; }
</style>
