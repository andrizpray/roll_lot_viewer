<template>
  <div class="upload-page">
    <h2>Upload & Import</h2>

    <div class="upload-section">
      <div
        class="dropzone"
        :class="{ active: isDragging }"
        @dragover.prevent="isDragging = true"
        @dragleave.prevent="isDragging = false"
        @drop.prevent="handleDrop"
      >
        <i class="pi pi-upload dropzone-icon"></i>
        <p>Drag & drop your Excel file here, or</p>
        <label for="file-upload" class="btn btn-primary">Browse Files</label>
        <input
          id="file-upload"
          type="file"
          accept=".xlsx,.xls"
          @change="handleFileSelect"
          hidden
        />
      </div>

      <div v-if="uploadProgress > 0" class="progress-section">
        <div class="progress-bar-wrapper">
          <div class="progress-bar" :style="{ width: uploadProgress + '%' }"></div>
        </div>
        <p class="progress-text">{{ progressText }}</p>
      </div>
    </div>

    <!-- Import History -->
    <div class="history-section">
      <h3>Import History</h3>
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
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="batch in importHistory" :key="batch.id">
            <td class="filename">{{ batch.filename }}</td>
            <td>
              <span :class="['type-badge', 'type-' + batch.type]">
                {{ batch.type === 'sheet' ? 'Sheet' : 'Roll' }}
              </span>
            </td>
            <td>
              <span :class="['status-badge', 'status-' + batch.status]">{{ batch.status }}</span>
            </td>
            <td>{{ batch.total_rows ?? '-' }}</td>
            <td>{{ batch.success_count ?? '-' }}</td>
            <td>{{ batch.failed_count ?? '-' }}</td>
            <td>{{ batch.created_at }}</td>
            <td>
              <button @click="viewDetails(batch)" class="btn btn-sm btn-secondary">Details</button>
            </td>
          </tr>
        </tbody>
      </table>
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
    progressText.value = 'Uploading...';

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
.upload-page {
  padding: 2rem 0;
}

.upload-page h2 {
  font-size: 1.75rem;
  color: #1e293b;
  margin-bottom: 2rem;
}

.upload-section {
  background: white;
  padding: 2rem;
  border-radius: 0.75rem;
  margin-bottom: 2rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.dropzone {
  border: 2px dashed #e5e7eb;
  border-radius: 0.75rem;
  padding: 3rem 2rem;
  text-align: center;
  transition: all 0.2s;
  cursor: pointer;
}

.dropzone.active {
  border-color: #1E40AF;
  background: #eef2ff;
}

.dropzone-icon {
  font-size: 3rem;
  color: #94a3b8;
  margin-bottom: 1rem;
}

.dropzone p {
  color: #64748b;
  margin-bottom: 1.5rem;
}

.btn {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem 1.5rem;
  border-radius: 0.5rem;
  font-weight: 500;
  cursor: pointer;
  border: none;
  transition: all 0.2s;
}

.btn-primary {
  background: #1E40AF;
  color: white;
}

.btn-primary:hover {
  background: #1E3A8A;
}

.btn-secondary {
  background: #f1f5f9;
  color: #475569;
}

.btn-secondary:hover {
  background: #e2e8f0;
}

.btn-sm {
  padding: 0.5rem 1rem;
  font-size: 0.875rem;
}

.btn-danger {
  background: #ef4444;
  color: white;
}

.btn-danger:hover {
  background: #dc2626;
}

.progress-section {
  margin-top: 1.5rem;
}

.progress-bar-wrapper {
  height: 0.75rem;
  background: #e5e7eb;
  border-radius: 0.375rem;
  overflow: hidden;
}

.progress-bar {
  height: 100%;
  background: #1E40AF;
  border-radius: 0.375rem;
  transition: width 0.3s ease;
}

.progress-text {
  margin-top: 0.5rem;
  color: #64748b;
  font-size: 0.875rem;
}

.history-section {
  background: white;
  padding: 2rem;
  border-radius: 0.75rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.history-section h3 {
  font-size: 1.25rem;
  color: #1e293b;
  margin-bottom: 1.5rem;
}

.history-table {
  width: 100%;
  border-collapse: collapse;
  min-width: 700px;
}

.history-table thead {
  background: #f8fafc;
  border-bottom: 2px solid #e5e7eb;
}

.history-table th {
  padding: 1rem;
  text-align: left;
  font-weight: 600;
  color: #475569;
}

.history-table td {
  padding: 1rem;
  border-bottom: 1px solid #e5e7eb;
}

.history-table tr:hover {
  background: #f8fafc;
}

.filename {
  max-width: 200px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.status-badge {
  display: inline-block;
  padding: 0.25rem 0.75rem;
  border-radius: 0.375rem;
  font-size: 0.875rem;
  font-weight: 500;
}

.status-processing {
  background: #e0e7ff;
  color: #3730a3;
}

.status-success {
  background: #dcfce7;
  color: #166534;
}

.status-failed {
  background: #fee2e2;
  color: #991b1b;
}

.type-badge {
  display: inline-block;
  padding: 0.25rem 0.75rem;
  border-radius: 0.375rem;
  font-size: 0.8rem;
  font-weight: 500;
}

.type-roll {
  background: #e0f2fe;
  color: #075985;
}

.type-sheet {
  background: #fae8ff;
  color: #86198f;
}
</style>
