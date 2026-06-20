<template>
  <div class="home-page">
    <div class="header">
      <h2>Paper Sheet Data</h2>
      <button @click="exportData" class="btn btn-primary" aria-label="Export results as CSV">
        <i class="pi pi-download"></i> Export CSV
      </button>
    </div>

    <!-- Mode Toggle -->
    <div class="filter-section">
      <div class="mode-toggle">
        <button
          @click="filterMode = 'batch'"
          :class="['mode-btn', { active: filterMode === 'batch' }]"
        >
          Batch Search
        </button>
        <button
          @click="filterMode = 'advanced'"
          :class="['mode-btn', { active: filterMode === 'advanced' }]"
        >
          Advanced Filter
        </button>
      </div>

      <!-- Mode A: Batch LotID Search -->
      <div v-if="filterMode === 'batch'" class="batch-filter">
        <label for="batch-lotids">Enter LotIDs (comma, newline, or semicolon separated):</label>
        <textarea
          id="batch-lotids"
          v-model="batchLotIds"
          placeholder="SHT001, SHT002&#10;SHT003"
          rows="5"
          class="input-textarea"
        ></textarea>
        <div class="batch-info">
          <span v-if="detectedLotIds.length > 0">
            {{ detectedLotIds.length }} LotID detected
          </span>
        </div>
        <button @click="searchBatch" class="btn btn-primary">Search</button>
      </div>

      <!-- Mode B: Advanced Filter -->
      <div v-else class="advanced-filter">
        <div class="filter-grid">
          <div class="filter-field">
            <label>Item ID:</label>
            <input v-model="filters.item_id" type="text" class="input-field" placeholder="ITEM001" />
          </div>
          <div class="filter-field">
            <label>Papertype:</label>
            <input v-model="filters.papertype" type="text" class="input-field" placeholder="BK, GB, CB, BSP..." />
          </div>
          <div class="filter-field">
            <label>Gramature:</label>
            <input v-model="filters.gramature" type="text" class="input-field" placeholder="BK350" />
          </div>
          <div class="filter-field">
            <label>Dimension:</label>
            <input v-model="filters.dimension" type="text" class="input-field" placeholder="590X840" />
          </div>
          <div class="filter-field">
            <label>From Date:</label>
            <input v-model="filters.date_from" type="date" class="input-field" />
          </div>
          <div class="filter-field">
            <label>To Date:</label>
            <input v-model="filters.date_to" type="date" class="input-field" />
          </div>
        </div>
        <button @click="currentPage = 1; searchAdvanced()" class="btn btn-primary">Search</button>
      </div>
    </div>

    <!-- Results -->
    <div v-if="batchNotFound.length > 0" class="notice notice-warning">
      <strong>{{ batchNotFound.length }} LotID not found:</strong>
      {{ batchNotFound.join(', ') }}
    </div>

    <div class="results-section" v-if="!isLoading && (sheets.length > 0 || batchNotFound.length > 0)">
      <div class="results-info">
        <span v-if="filterMode === 'batch'">
          Found: {{ sheets.length }} / {{ detectedLotIds.length }} LotIDs
        </span>
        <span v-else>
          Found: {{ totalItems }} items
        </span>
        <button
          v-if="sheets.length > 0"
          @click="exportData"
          class="btn btn-sm btn-primary"
          aria-label="Download results as CSV"
        >
          <i class="pi pi-download"></i> Download CSV
        </button>
      </div>

      <!-- Loading Skeleton -->
      <div v-if="isLoading" class="skeleton-table">
        <div v-for="n in 8" :key="'skel-'+n" class="skeleton-row">
          <div class="skeleton-cell" v-for="m in 8" :key="'skel-c-'+m">
            <div class="skeleton-bar shimmer"></div>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-if="!isLoading && sheets.length === 0 && !batchNotFound.length" class="empty-state">
        <i class="pi pi-inbox empty-icon"></i>
        <h3>No Data Found</h3>
        <p>Upload a Mutasi Stock Sheet file first or adjust your filter criteria.</p>
        <router-link to="/upload" class="btn btn-primary">Go to Upload</router-link>
      </div>

      <div class="table-wrapper" v-if="!isLoading && sheets.length > 0">
      <table class="data-table">
        <thead>
          <tr>
            <th>LotID</th>
            <th>ItemID</th>
            <th>Weight</th>
            <th>Papertype</th>
            <th>Gramature</th>
            <th>Dimension</th>
            <th>Content Pack</th>
            <th>Content Pallet</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="sheet in sheets" :key="sheet.id" class="row-hover">
            <td>{{ sheet.lot_id }}</td>
            <td>{{ sheet.item_id }}</td>
            <td>{{ sheet.weight }}</td>
            <td>{{ sheet.papertype || '-' }}</td>
            <td>{{ sheet.gramature }}</td>
            <td>{{ sheet.dimension }}</td>
            <td>{{ sheet.content_pack ?? '-' }}</td>
            <td>{{ sheet.content_pallet ?? '-' }}</td>
            <td>
              <button @click="showDetail(sheet)" class="btn btn-sm btn-secondary" aria-label="View details">
                <i class="pi pi-eye"></i>
              </button>
            </td>
          </tr>
        </tbody>
      </table>
      </div>

      <!-- Pagination (Advanced mode only) -->
      <div v-if="filterMode === 'advanced' && pagination" class="pagination">
        <button
          v-for="page in pagination.last_page"
          :key="page"
          @click="currentPage = page; searchAdvanced()"
          :class="['page-btn', { active: page === currentPage }]"
        >
          {{ page }}
        </button>
      </div>
    </div>

    <!-- Detail Modal -->
    <SheetDetailModal v-if="selectedSheet" :sheet="selectedSheet" @close="selectedSheet = null" />
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import axios from 'axios';
import SheetDetailModal from '../components/SheetDetailModal.vue';

const filterMode = ref('advanced');
const batchLotIds = ref('');
const filters = ref({
  item_id: '',
  papertype: '',
  gramature: '',
  dimension: '',
  date_from: '',
  date_to: '',
});

const sheets = ref([]);
const pagination = ref(null);
const currentPage = ref(1);
const selectedSheet = ref(null);
const batchNotFound = ref([]);
const totalItems = ref(0);
const isLoading = ref(false);

const detectedLotIds = computed(() => {
  const input = batchLotIds.value;
  if (!input.trim()) return [];
  const lotIds = input.split(/[\s,;]+/).filter(id => id.trim());
  return [...new Set(lotIds.map(id => id.toUpperCase()))];
});

const searchBatch = async () => {
  isLoading.value = true;
  const lotIdsParam = detectedLotIds.value.join(',');
  try {
    const response = await axios.get('/api/sheets', {
      params: {
        mode: 'batch',
        lot_ids: lotIdsParam,
      },
    });
    sheets.value = response.data.data;
    batchNotFound.value = response.data.meta.not_found || [];
  } catch (error) {
    console.error('Batch search failed:', error);
  } finally {
    isLoading.value = false;
  }
};

const searchAdvanced = async () => {
  isLoading.value = true;
  try {
    const params = { mode: 'advanced', page: currentPage.value };
    Object.keys(filters.value).forEach(key => {
      if (filters.value[key]) {
        params[key] = filters.value[key];
      }
    });
    const response = await axios.get('/api/sheets', { params });
    sheets.value = response.data.data;
    pagination.value = {
      last_page: response.data.last_page,
      current_page: response.data.current_page,
    };
    totalItems.value = response.data.total;
  } catch (error) {
    console.error('Advanced search failed:', error);
  } finally {
    isLoading.value = false;
  }
};

const exportData = async () => {
  try {
    const params = { resource: 'sheet', mode: filterMode.value };
    if (filterMode.value === 'batch') {
      params.lot_ids = detectedLotIds.value.join(',');
    } else {
      Object.keys(filters.value).forEach(key => {
        if (filters.value[key]) {
          params[key] = filters.value[key];
        }
      });
    }
    const response = await axios.get('/api/export', {
      params,
      responseType: 'blob',
    });
    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', `paper_sheets_${new Date().getTime()}.xlsx`);
    document.body.appendChild(link);
    link.click();
    link.parentElement.removeChild(link);
  } catch (error) {
    console.error('Export failed:', error);
  }
};

const showDetail = (sheet) => {
  selectedSheet.value = sheet;
};
</script>

<style scoped>
.home-page {
  padding: 2rem 0;
}

.header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 2rem;
}

.header h2 {
  font-size: 1.75rem;
  color: #1e293b;
}

.filter-section {
  background: white;
  padding: 2rem;
  border-radius: 0.75rem;
  margin-bottom: 2rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.mode-toggle {
  display: flex;
  gap: 1rem;
  margin-bottom: 2rem;
  border-bottom: 2px solid #e5e7eb;
  padding-bottom: 1rem;
}

.mode-btn {
  padding: 0.75rem 1.5rem;
  border: none;
  background: none;
  color: #64748b;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
  border-bottom: 3px solid transparent;
  margin-bottom: -1rem;
}

.mode-btn.active {
  color: #1E40AF;
  border-bottom-color: #1E40AF;
}

.mode-btn:hover {
  color: #1E40AF;
}

.batch-filter,
.advanced-filter {
  animation: fadeIn 0.2s ease-in;
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

.batch-filter label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 500;
  color: #334155;
}

.input-textarea {
  width: 100%;
  padding: 0.75rem;
  border: 1px solid #e5e7eb;
  border-radius: 0.5rem;
  font-family: monospace;
  margin-bottom: 0.5rem;
  resize: vertical;
}

.batch-info {
  padding: 0.5rem 0;
  font-size: 0.875rem;
  color: #64748b;
  margin-bottom: 1rem;
}

.filter-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.filter-field {
  display: flex;
  flex-direction: column;
}

.filter-field label {
  font-weight: 500;
  color: #334155;
  margin-bottom: 0.5rem;
}

.input-field {
  padding: 0.75rem;
  border: 1px solid #e5e7eb;
  border-radius: 0.5rem;
  font-size: 1rem;
}

.input-field:focus {
  outline: none;
  border-color: #1E40AF;
  box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1);
}

.btn {
  padding: 0.75rem 1.5rem;
  border: none;
  border-radius: 0.5rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
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

.notice {
  padding: 1rem;
  border-radius: 0.5rem;
  margin-bottom: 1.5rem;
}

.notice-warning {
  background: #fef3c7;
  color: #92400e;
  border: 1px solid #fcd34d;
}

.results-section {
  background: white;
  padding: 2rem;
  border-radius: 0.75rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.results-info {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
  font-size: 0.875rem;
  color: #64748b;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
  min-width: 800px;
}

.table-wrapper {
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
}

.data-table thead {
  background: #f8fafc;
  border-bottom: 2px solid #e5e7eb;
}

.data-table th {
  padding: 1rem;
  text-align: left;
  font-weight: 600;
  color: #475569;
}

.data-table td {
  padding: 1rem;
  border-bottom: 1px solid #e5e7eb;
}

.row-hover:hover {
  background: #f8fafc;
}

.pagination {
  display: flex;
  justify-content: center;
  gap: 0.5rem;
  margin-top: 2rem;
  padding-top: 1rem;
  border-top: 1px solid #e5e7eb;
}

.page-btn {
  padding: 0.5rem 1rem;
  border: 1px solid #e5e7eb;
  border-radius: 0.375rem;
  background: white;
  cursor: pointer;
  transition: all 0.2s;
}

.page-btn:hover {
  border-color: #1E40AF;
  color: #1E40AF;
}

.page-btn.active {
  background: #1E40AF;
  color: white;
  border-color: #1E40AF;
}

.btn:focus-visible,
.mode-btn:focus-visible,
.input-field:focus-visible,
.input-textarea:focus-visible,
.page-btn:focus-visible {
  outline: 2px solid #1E40AF;
  outline-offset: 2px;
}

@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after {
    animation-duration: 0.01ms !important;
    transition-duration: 0.01ms !important;
  }
}

.skeleton-table {
  background: white;
  border-radius: 0.75rem;
  padding: 1rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.skeleton-row {
  display: grid;
  grid-template-columns: repeat(8, 1fr);
  gap: 1rem;
  padding: 0.75rem 1rem;
}

.skeleton-bar {
  height: 1rem;
  border-radius: 0.25rem;
  background: #e5e7eb;
}

.shimmer {
  animation: shimmer 1.5s infinite;
  background: linear-gradient(90deg, #e5e7eb 25%, #f1f5f9 50%, #e5e7eb 75%);
  background-size: 200% 100%;
}

@keyframes shimmer {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

.empty-state {
  text-align: center;
  padding: 4rem 2rem;
  background: white;
  border-radius: 0.75rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.empty-icon {
  font-size: 4rem;
  color: #cbd5e1;
  margin-bottom: 1rem;
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
</style>
