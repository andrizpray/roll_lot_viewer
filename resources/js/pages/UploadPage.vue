<template>
  <div class="upload-page">
    <div class="header">
      <h2>Upload Excel File</h2>
    </div>

    <!-- Upload Area -->
    <div class="upload-section">
      <div
        class="drop-zone"
        @dragover="handleDragOver"
        @dragleave="handleDragLeave"
        @drop="handleDrop"
        @click="$refs.fileInput.click()"
      >
        <i class="pi pi-upload upload-icon"></i>
        <p class="drop-text">
          Drag & drop Excel file here or <span class="browse-text">browse</span>
        </p>
        <input
          ref="fileInput"
          type="file"
          class="file-input"
          accept=".xlsx,.xls"
          @change="handleFileSelect"
        />
      </div>

      <div v-if="selectedFile" class="file-info">
        <p><strong>Selected:</strong> {{ selectedFile.name }}</p>
        <p><strong>Size:</strong> {{ formatFileSize(selectedFile.size) }}</p>
        <button @click="removeFile" class="btn btn-danger">Remove</button>
      </div>
    </div>

    <!-- Upload Button -->
    <button
      @click="uploadFile"
      :disabled="!selectedFile || isUploading"
      class="btn btn-primary"
    >
      <i v-if="isUploading" class="pi pi-spin pi-spinner"></i>
      {{ isUploading ? 'Uploading...' : 'Upload & Process' }}
    </button>

    <!-- Progress -->
    <div v-if="isUploading" class="progress-section">
      <div class="progress-bar">
        <div class="progress-fill" :style="{ width: uploadProgress + '%' }"></div>
      </div>
      <p class="progress-text">{{ progressText }}</p>
    </div>

    <!-- Results -->
    <div v-if="lastBatch" class="results-section">
      <h3>Import Results</h3>
      <div class="result-cards">
        <div class="result-card success">
          <i class="pi pi-check-circle"></i>
          <div class="card-content">
            <span class="label">Status</span>
            <span class="value" :class="'status-' + lastBatch.status">{{ lastBatch.status }}</span>
          </div>
        </div>
        <div class="result-card">
          <i class="pi pi-file-excel"></i>
          <div class="card-content">
            <span class="label">Filename</span>
            <span class="value">{{ lastBatch.filename }}</span>
          </div>
        </div>
        <div class="result-card">
          <i class="pi pi-list"></i>
          <div class="card-content">
            <span class="label">Total Rows</span>
            <span class="value">{{ lastBatch.total_rows }}</span>
          </div>
        </div>
        <div class="result-card success">
          <i class="pi pi-check"></i>
          <div class="card-content">
            <span class="label">Success</span>
            <span class="value">{{ lastBatch.success_count }}</span>
          </div>
        </div>
        <div class="result-card warning">
          <i class="pi pi-exclamation-triangle"></i>
          <div class="card-content">
            <span class="label">Failed</span>
            <span class="value">{{ lastBatch.failed_count }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Import History -->
    <div class="history-section">
      <div class="history-header">
        <h3>Import History</h3>
        <button @click="refreshHistory" class="btn btn-sm btn-secondary">
          <i class="pi pi-refresh"></i> Refresh
        </button>
      </div>

      <!-- Empty State -->
      <div v-if="!loadingHistory && importHistory.length === 0" class="empty-state">
        <i class="pi pi-inbox empty-icon"></i>
        <h3>No Import History</h3>
        <p>Upload an Excel file above to get started.</p>
      </div>

      <div v-if="loadingHistory" class="loading-text">Loading history...</div>

      <table v-if="importHistory.length > 0" class="history-table">
        <thead>
          <tr>
            <th>Filename</th>
            <th>Date</th>
            <th>Total</th>
            <th>Success</th>
            <th>Failed</th>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="batch in importHistory" :key="batch.id">
            <td>{{ batch.filename }}</td>
            <td>{{ formatDate(batch.created_at) }}</td>
            <td>{{ batch.total_rows }}</td>
            <td>{{ batch.success_count }}</td>
            <td>{{ batch.failed_count }}</td>
            <td>
              <span :class="['status-badge', 'status-' + batch.status]">
                {{ batch.status }}
              </span>
            </td>
            <td>
              <button @click="showDetails(batch)" class="btn btn-sm btn-secondary">
                <i class="pi pi-eye"></i>
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- History Modal -->
    <ImportBatchModal
      v-if="showHistoryModal"
      :batch="currentBatch"
      :errors="currentErrors"
      @close="showHistoryModal = false"
    />
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import ImportBatchModal from '../components/ImportBatchModal.vue';

const fileInput = ref(null);
const selectedFile = ref(null);
const isUploading = ref(false);
const uploadProgress = ref(0);
const progressText = ref('');
const lastBatch = ref(null);
const importHistory = ref([]);
const showHistoryModal = ref(false);
const currentBatch = ref(null);
const currentErrors = ref([]);
const loadingHistory = ref(false);

const handleDragOver = (e) => {
  e.preventDefault();
};

const handleDragLeave = (e) => {
  e.preventDefault();
};

const handleDrop = (e) => {
  e.preventDefault();
  if (e.dataTransfer.files && e.dataTransfer.files[0]) {
    selectedFile.value = e.dataTransfer.files[0];
  }
};

const handleFileSelect = (e) => {
  if (e.target.files && e.target.files[0]) {
    selectedFile.value = e.target.files[0];
  }
};

const removeFile = () => {
  selectedFile.value = null;
  fileInput.value.value = '';
};

const formatFileSize = (bytes) => {
  if (bytes < 1024) return bytes + ' B';
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
  return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
};

const formatDate = (dateString) => {
  return new Date(dateString).toLocaleDateString('id-ID', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};

const uploadFile = async () => {
  if (!selectedFile.value) return;

  isUploading.value = true;
  uploadProgress.value = 0;
  progressText.value = 'Starting upload...';

  const formData = new FormData();
  formData.append('file', selectedFile.value);

  try {
    const response = await axios.post('/api/imports', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
      onUploadProgress: (e) => {
        if (e.total) {
          uploadProgress.value = Math.round((e.loaded * 100) / e.total);
          progressText.value = `Uploading... ${uploadProgress.value}%`;
        }
      },
    });

    uploadProgress.value = 100;
    progressText.value = 'Processing in background...';

    // Start polling status
    pollStatus(response.data.batch_id);
  } catch (error) {
    isUploading.value = false;
    progressText.value = 'Upload failed';
    console.error('Upload failed:', error);
  }
};

const pollStatus = async (batchId) => {
  let retryCount = 0;
  const maxRetries = 5;
  const checkStatus = async () => {
    try {
      const response = await axios.get(`/api/imports/${batchId}/status`);
      const data = response.data;

      if (data.status === 'success') {
        isUploading.value = false;
        progressText.value = 'Import completed successfully';
        lastBatch.value = data;
        fetchHistory();
      } else if (data.status === 'failed') {
        isUploading.value = false;
        progressText.value = 'Import failed';
      } else {
        // Still processing
        progressText.value = `Processing... ${data.success_count + data.failed_count}/${data.total_rows} rows processed`;
        retryCount = 0;
        setTimeout(checkStatus, 2000);
      }
    } catch (error) {
      console.error('Status check failed:', error);
      if (retryCount < maxRetries) {
        retryCount++;
        setTimeout(checkStatus, 3000);
      } else {
        isUploading.value = false;
        progressText.value = 'Status check failed after multiple retries';
      }
    }
  };

  checkStatus();
};

const fetchHistory = async () => {
  loadingHistory.value = true;
  try {
    const response = await axios.get('/api/imports');
    importHistory.value = response.data.data || [];
  } catch (error) {
    console.error('Failed to fetch history:', error);
  } finally {
    loadingHistory.value = false;
  }
};

const showDetails = async (batch) => {
  try {
    const response = await axios.get(`/api/imports/${batch.id}`);
    currentBatch.value = response.data;
    currentErrors.value = response.data.errors || [];
    showHistoryModal.value = true;
  } catch (error) {
    console.error('Failed to fetch batch details:', error);
  }
};

const refreshHistory = () => {
  fetchHistory();
};

onMounted(() => {
  fetchHistory();
});
</script>

<style scoped>
.upload-page {
  padding: 2rem 0;
}

.header {
  margin-bottom: 2rem;
}

.header h2 {
  font-size: 1.75rem;
  color: #1e293b;
}

.upload-section {
  background: white;
  padding: 2rem;
  border-radius: 0.75rem;
  margin-bottom: 2rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.drop-zone {
  border: 2px dashed #cbd5e1;
  border-radius: 0.75rem;
  padding: 3rem 2rem;
  text-align: center;
  cursor: pointer;
  transition: all 0.3s;
  position: relative;
}

.drop-zone:hover {
  border-color: #0ea5e9;
  background: #f8fafc;
}

.drop-zone .file-input {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  opacity: 0;
  cursor: pointer;
}

.upload-icon {
  font-size: 4rem;
  color: #cbd5e1;
  margin-bottom: 1rem;
}

.drop-text {
  color: #64748b;
  font-size: 1.125rem;
}

.browse-text {
  color: #0ea5e9;
  font-weight: 600;
}

.file-info {
  margin-top: 1.5rem;
  padding: 1rem;
  background: #f8fafc;
  border-radius: 0.5rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.file-info p {
  margin: 0.25rem 0;
  color: #475569;
}

.progress-section {
  margin: 2rem 0;
}

.progress-bar {
  width: 100%;
  height: 0.75rem;
  background: #e5e7eb;
  border-radius: 0.375rem;
  overflow: hidden;
}

.progress-fill {
  height: 100%;
  background: #0ea5e9;
  transition: width 0.3s ease;
}

/* Empty State */
.empty-state {
  text-align: center;
  padding: 4rem 2rem;
  background: white;
  border-radius: 0.75rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.empty-state h3 {
  font-size: 1.25rem;
  color: #475569;
  margin-bottom: 0.5rem;
}

.empty-state p {
  color: #94a3b8;
  margin-bottom: 1.5rem;
}

.empty-icon {
  font-size: 4rem;
  color: #cbd5e1;
  margin-bottom: 1rem;
}

.loading-text {
  text-align: center;
  padding: 2rem;
  color: #64748b;
  font-style: italic;
}

.results-section {
  background: white;
  padding: 2rem;
  border-radius: 0.75rem;
  margin-bottom: 2rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.results-section h3 {
  margin-bottom: 1.5rem;
  font-size: 1.25rem;
  color: #1e293b;
}

.result-cards {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: 1rem;
}

.result-card {
  padding: 1.5rem;
  border-radius: 0.5rem;
  text-align: center;
}

.result-card i {
  font-size: 2rem;
  margin-bottom: 0.5rem;
}

.card-content .label {
  display: block;
  font-size: 0.75rem;
  color: #64748b;
  margin-bottom: 0.25rem;
}

.card-content .value {
  font-size: 1.5rem;
  font-weight: 600;
  color: #1e293b;
}

.result-card.success {
  background: #dcfce7;
}

.result-card.success i {
  color: #166534;
}

.result-card.warning {
  background: #fef3c7;
}

.result-card.warning i {
  color: #92400e;
}

.result-card i {
  color: #0ea5e9;
}

.result-card.warning .value {
  color: #92400e;
}

.result-card.success .value {
  color: #166534;
}

.result-card i {
  color: #0ea5e9;
}

.history-section {
  background: white;
  padding: 2rem;
  border-radius: 0.75rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.history-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
}

.history-header h3 {
  font-size: 1.25rem;
  color: #1e293b;
}

.history-table {
  width: 100%;
  border-collapse: collapse;
}

.history-table th {
  padding: 1rem;
  text-align: left;
  font-weight: 600;
  color: #475569;
  border-bottom: 2px solid #e5e7eb;
}

.history-table td {
  padding: 1rem;
  border-bottom: 1px solid #e5e7eb;
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
</style>
