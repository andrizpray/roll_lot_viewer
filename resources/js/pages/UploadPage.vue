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

    <div class="upload-section">
      <div
        class="dropzone"
        :class="{ 'dropzone-active': isDragging, 'dropzone-done': uploadProgress === 100 }"
        @dragover.prevent="isDragging = true"
        @dragleave.prevent="isDragging = false"
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
          <label for="file-upload" class="btn btn-primary">
            <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
              <polyline points="14 2 14 8 20 8"/>
            </svg>
            Browse Files
          </label>
          <input
            id="file-upload"
            type="file"
            accept=".xlsx,.xls"
            @change="handleFileSelect"
            hidden
          />
        </div>
      </div>

      <div v-if="uploadProgress > 0" class="progress-section">
        <div class="progress-header">
          <span class="progress-label">
            <svg class="inline-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
              <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
            </svg>
            {{ progressText }}
          </span>
          <span class="progress-pct">{{ uploadProgress }}%</span>
        </div>
        <div class="progress-track">
          <div class="progress-fill" :style="{ width: uploadProgress + '%' }" :class="{ 'progress-done': uploadProgress === 100 }"></div>
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
import axios from 'axios';
import ImportBatchModal from '../components/ImportBatchModal.vue';

const selectedImport = ref(null);
const importHistory = ref([]);
const uploadProgress = ref(0);
const progressText = ref('');
const isDragging = ref(false);

const loadHistory = async () => {
  try {
    const response = await axios.get('/api/imports');
    importHistory.value = response.data.data || [];
  } catch (error) {
    console.error('Failed to load import history:', error);
  }
};

const uploadFile = async (file) => {
  const formData = new FormData();
  formData.append('file', file);

  try {
    uploadProgress.value = 1;
    progressText.value = 'Uploading to server...';

    const response = await axios.post('/api/imports', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
      onUploadProgress: (progressEvent) => {
        if (progressEvent.total) {
          uploadProgress.value = Math.round((progressEvent.loaded * 100) / progressEvent.total);
        }
      },
    });

    const batchId = response.data.batch_id;
    progressText.value = 'Processing file...';

    // Poll for completion
    let retryCount = 0;
    const checkStatus = async () => {
      try {
        const statusRes = await axios.get(`/api/imports/${batchId}/status`);
        const status = statusRes.data.status;

        if (status === 'success') {
          uploadProgress.value = 100;
          progressText.value = `Import selesai! ${statusRes.data.success_count} berhasil, ${statusRes.data.failed_count} gagal.`;
          retryCount = 0; // reset retry counter setelah request berhasil
          await loadHistory();
        } else if (status === 'failed') {
          progressText.value = 'Import gagal.';
          await loadHistory();
        } else {
          throw new Error('Still processing');
        }
      } catch (error) {
        if (retryCount < 30) {
          retryCount++;
          setTimeout(checkStatus, 2000);
        } else {
          progressText.value = 'Gagal memantau status import. Coba refresh halaman.';
        }
      }
    };

    checkStatus();
  } catch (error) {
    uploadProgress.value = 0;
    progressText.value = 'Upload failed: ' + (error.response?.data?.message || error.message);
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
.dropzone:hover {
  border-color: #059669;
  background: #ecfdf5;
}
.dropzone-active {
  border-color: #059669 !important;
  background: #ecfdf5 !important;
  transform: scale(1.02);
  box-shadow: 0 0 0 4px rgba(5, 150, 105, 0.15);
}
.dropzone-done {
  border-color: #16a34a;
  background: #f0fdf4;
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
.dropzone-text {
  font-size: 1.1rem;
  font-weight: 600;
  color: #475569;
  margin: 0;
}
.dropzone-hint {
  font-size: 0.85rem;
  color: #94a3b8;
  margin: 0 0 0.5rem 0;
}

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
.btn-outline {
  background: transparent;
  color: #059669;
  border: 1.5px solid #059669;
}
.btn-outline:hover { background: #ecfdf5; }
.btn-sm { padding: 0.4rem 0.75rem; font-size: 0.8rem; }
.btn-icon { width: 1.2rem; height: 1.2rem; }
.inline-icon { width: 1rem; height: 1rem; margin-right: 0.3rem; vertical-align: middle; }

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
  gap: 0.3rem;
}
.progress-pct {
  font-size: 0.85rem;
  font-weight: 700;
  color: #059669;
}
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
.progress-done {
  background: linear-gradient(90deg, #16a34a, #22c55e);
}

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
.history-table td {
  padding: 0.75rem 1rem;
  border-bottom: 1px solid #f1f5f9;
  font-size: 0.9rem;
}
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
