<template>
  <div class="home-page fade-in">
    <div class="page-header">
      <div class="page-header-left">
        <h2 class="page-title">
          <svg class="page-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
            <polyline points="14 2 14 8 20 8"/>
            <line x1="8" y1="13" x2="16" y2="13"/>
            <line x1="8" y1="17" x2="12" y2="17"/>
          </svg>
          Paper Sheet Data
        </h2>
        <span class="count-badge" v-if="totalItems > 0">{{ totalItems.toLocaleString() }} records</span>
      </div>
      <button @click="exportData" class="btn btn-primary" aria-label="Export results as CSV">
        <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
          <polyline points="7 10 12 15 17 10"/>
          <line x1="12" y1="15" x2="12" y2="3"/>
        </svg>
        Export CSV
      </button>
    </div>

    <!-- Mode Toggle -->
    <div class="card card-pad filter-section">
      <div class="mode-toggle">
        <button
          @click="filterMode = 'batch'"
          :class="['mode-btn', { active: filterMode === 'batch' }]"
        >
          <svg class="tab-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="15 3 21 3 21 9"/>
            <polyline points="9 21 3 21 3 15"/>
            <line x1="21" y1="3" x2="14" y2="10"/>
            <line x1="3" y1="21" x2="10" y2="14"/>
          </svg>
          Batch Search
        </button>
        <button
          @click="filterMode = 'advanced'"
          :class="['mode-btn', { active: filterMode === 'advanced' }]"
        >
          <svg class="tab-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="4" y1="21" x2="4" y2="14"/>
            <line x1="4" y1="10" x2="4" y2="3"/>
            <line x1="12" y1="21" x2="12" y2="12"/>
            <line x1="12" y1="8" x2="12" y2="3"/>
            <line x1="20" y1="21" x2="20" y2="16"/>
            <line x1="20" y1="12" x2="20" y2="3"/>
            <line x1="1" y1="14" x2="7" y2="14"/>
            <line x1="9" y1="8" x2="15" y2="8"/>
            <line x1="17" y1="16" x2="23" y2="16"/>
          </svg>
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
            <span class="badge-detected">{{ detectedLotIds.length }} LotID detected</span>
          </span>
        </div>
        <button @click="searchBatch" class="btn btn-primary">Search</button>
      </div>

      <!-- Mode B: Advanced Filter -->
      <div v-else class="advanced-filter">
        <div class="filter-grid">
          <div class="filter-field">
            <label>Item ID:</label>
            <select v-model="filters.item_id" class="input-field">
              <option value="">All</option>
              <option v-for="val in distinctValues.item_ids" :key="val" :value="val">{{ val }}</option>
            </select>
          </div>
          <div class="filter-field">
            <label>Papertype:</label>
            <select v-model="filters.papertype" class="input-field">
              <option value="">All</option>
              <option v-for="val in distinctValues.papertypes" :key="val" :value="val">{{ val }}</option>
            </select>
          </div>
          <div class="filter-field">
            <label>Gramature:</label>
            <select v-model="filters.gramature" class="input-field">
              <option value="">All</option>
              <option v-for="val in distinctValues.gramatures" :key="val" :value="val">{{ val }}</option>
            </select>
          </div>
          <div class="filter-field">
            <label>Dimension:</label>
            <select v-model="filters.dimension" class="input-field">
              <option value="">All</option>
              <option v-for="val in distinctValues.dimensions" :key="val" :value="val">{{ val }}</option>
            </select>
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
        <div class="filter-actions">
          <button @click="currentPage = 1; searchAdvanced()" class="btn btn-primary">
            <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
            </svg>
            Search
          </button>
          <button @click="resetFilters" class="btn btn-ghost">Reset</button>
        </div>
      </div>
    </div>

    <!-- Results -->
    <div v-if="batchNotFound.length > 0" class="notice notice-warning">
      <svg class="notice-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
        <line x1="12" y1="9" x2="12" y2="13"/>
        <line x1="12" y1="17" x2="12.01" y2="17"/>
      </svg>
      <div>
        <strong>{{ batchNotFound.length }} LotID not found:</strong>
        {{ batchNotFound.join(', ') }}
      </div>
    </div>

    <div class="card card-pad results-section" v-if="!isLoading && (sheets.length > 0 || batchNotFound.length > 0)">
      <div class="results-info">
        <span v-if="filterMode === 'batch'">
          Found: <strong>{{ sheets.length }}</strong> / {{ detectedLotIds.length }} LotIDs
        </span>
        <span v-else>
          Found: <strong>{{ totalItems }}</strong> items
        </span>
        <button
          v-if="sheets.length > 0"
          @click="exportData"
          class="btn btn-sm btn-outline"
          aria-label="Download data"
        >
          <svg class="btn-icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
            <polyline points="7 10 12 15 17 10"/>
            <line x1="12" y1="15" x2="12" y2="3"/>
          </svg>
          Download Data
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

      <div class="table-wrapper" v-if="!isLoading && sheets.length > 0">
      <table class="data-table">
        <thead>
          <tr>
            <th>LotID</th>
            <th>ItemID</th>
            <th class="col-weight">Weight</th>
            <th>Papertype</th>
            <th>Gramature</th>
            <th>Dimension</th>
            <th>Content Pack</th>
            <th>Content Pallet</th>
            <th class="col-action">Action</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="sheet in sheets" :key="sheet.id" class="row-hover">
            <td class="cell-lotid">{{ sheet.lot_id }}</td>
            <td>{{ sheet.item_id }}</td>
            <td class="cell-weight">
              <span :class="weightClass(sheet.weight)">{{ sheet.weight }}</span>
            </td>
            <td>{{ sheet.papertype || '-' }}</td>
            <td>{{ sheet.gramature }}</td>
            <td>{{ sheet.dimension }}</td>
            <td class="cell-muted">{{ sheet.content_pack ?? '-' }}</td>
            <td class="cell-muted">{{ sheet.content_pallet ?? '-' }}</td>
            <td class="col-action">
              <button @click="showDetail(sheet)" class="btn btn-icon-only" title="View details" aria-label="View details">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
                  <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                  <circle cx="12" cy="12" r="3"/>
                </svg>
              </button>
            </td>
          </tr>
        </tbody>
      </table>
      </div>

      <!-- Pagination (Advanced mode only) -->
      <div v-if="filterMode === 'advanced' && pagination" class="pagination">
        <button
          v-if="currentPage > 1"
          @click="currentPage--; searchAdvanced()"
          class="page-btn"
        >
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
            <polyline points="15 18 9 12 15 6"/>
          </svg>
          Prev
        </button>
        <template v-for="page in pagination.last_page" :key="page">
          <button
            v-if="page === 1 || page === pagination.last_page || Math.abs(page - currentPage) <= 2"
            @click="currentPage = page; searchAdvanced()"
            :class="['page-btn', { active: page === currentPage }]"
          >
            {{ page }}
          </button>
          <span v-else-if="page === currentPage - 3 || page === currentPage + 3" class="page-dots">...</span>
        </template>
        <button
          v-if="currentPage < pagination.last_page"
          @click="currentPage++; searchAdvanced()"
          class="page-btn"
        >
          Next
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
            <polyline points="9 18 15 12 9 6"/>
          </svg>
        </button>
      </div>
    </div>

    <!-- Empty State -->
    <div v-if="!isLoading && sheets.length === 0 && !batchNotFound.length" class="empty-state">
      <svg class="empty-icon-svg" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.5">
        <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
        <polyline points="14 2 14 8 20 8"/>
        <line x1="8" y1="13" x2="16" y2="13"/>
        <line x1="8" y1="17" x2="12" y2="17"/>
      </svg>
      <h3>No Data Found</h3>
      <p>Upload a Mutasi Stock Sheet file first or adjust your filter criteria.</p>
      <router-link to="/upload" class="btn btn-primary">Go to Upload</router-link>
    </div>

    <!-- Detail Modal -->
    <SheetDetailModal v-if="selectedSheet" :sheet="selectedSheet" @close="selectedSheet = null" />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
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

// Distinct values for dropdown options
const distinctValues = ref({
  papertypes: [],
  gramatures: [],
  dimensions: [],
  item_ids: [],
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
  const unique = [...new Set(lotIds.map(id => id.toUpperCase()))];
  return unique.slice(0, 1000); // Limit to 1000 IDs to prevent DoS
});

const fetchDistinctValues = async () => {
  try {
    const response = await axios.get('/api/sheets/distinct-values');
    distinctValues.value = response.data;
  } catch (error) {
    console.error('Failed to fetch distinct values:', error);
  }
};

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

const resetFilters = () => {
  filters.value = {
    item_id: '',
    papertype: '',
    gramature: '',
    dimension: '',
    date_from: '',
    date_to: '',
  };
  currentPage.value = 1;
  searchAdvanced();
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

    // Create export job
    const response = await axios.get('/api/export', { params });
    const jobId = response.data.job_id;

    // Poll for completion
    let attempts = 0;
    const maxAttempts = 120; // 2 minutes

    while (attempts < maxAttempts) {
      await new Promise(resolve => setTimeout(resolve, 1000));
      const statusRes = await axios.get(`/api/export/${jobId}/status`);
      const status = statusRes.data.status;

      if (status === 'completed') {
        // Download the file
        window.location.href = `/api/export/${jobId}/download`;
        return;
      }

      if (status === 'failed') {
        throw new Error(statusRes.data.error_message || 'Export failed');
      }

      attempts++;
    }

    throw new Error('Export timeout');
  } catch (error) {
    console.error('Export failed:', error);
    alert('Export gagal: ' + (error.message || 'Unknown error'));
  }
};

const showDetail = (sheet) => {
  selectedSheet.value = sheet;
};

// Weight color classes
const weightClass = (w) => {
  const n = Number(w);
  if (n >= 1500) return 'weight-heavy';
  if (n >= 800) return 'weight-medium';
  return 'weight-light';
};

onMounted(() => {
  fetchDistinctValues();
  searchAdvanced();
});
</script>

<style scoped>
.home-page { padding: 0; }

.filter-section { margin-bottom: 1.5rem; }
.batch-filter, .advanced-filter { animation: fadeIn 0.2s ease-in; }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

.batch-filter label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 500;
  color: var(--text-body);
  font-size: 0.9rem;
}
.input-textarea { margin-bottom: 0.5rem; }
.batch-info { padding: 0.5rem 0; font-size: 0.875rem; color: var(--text-muted); margin-bottom: 0.75rem; }
.badge-detected {
  background: var(--primary-light); color: var(--primary);
  padding: 0.2rem 0.75rem; border-radius: 999px; font-weight: 600; font-size: 0.8rem;
}

.filter-grid { margin-bottom: 1.25rem; }
.filter-field { display: flex; flex-direction: column; }
.filter-field label {
  font-weight: 600;
  color: var(--text-body);
  margin-bottom: 0.4rem;
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.filter-actions { display: flex; gap: 0.75rem; }

.results-info {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
  font-size: 0.9rem;
  color: var(--text-body);
}
.data-table { min-width: 900px; }
.col-weight { width: 100px; }
</style>
