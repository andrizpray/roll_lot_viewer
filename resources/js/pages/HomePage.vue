<template>
  <div class="home-page fade-in">
    <div class="page-header">
      <div class="page-header-left">
        <h2 class="page-title">
          <svg class="page-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <ellipse cx="12" cy="7" rx="8" ry="4"/>
            <path d="M20 7v4c0 2.2-3.6 4-8 4s-8-1.8-8-4V7"/>
            <path d="M4 11v4c0 2.2 3.6 4 8 4s8-1.8 8-4v-4"/>
          </svg>
          Roll Lot Data
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
          Batch LotID Search
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
          placeholder="E312345, E312346&#10;E312347"
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
            <label>Grade:</label>
            <div class="searchable-select">
              <div class="grade-tags" v-if="filters.grade.length > 0">
                <span v-for="g in filters.grade" :key="g" class="grade-tag">
                  {{ g }}
                  <button @click="removeGrade(g)" class="grade-tag-remove">&times;</button>
                </span>
              </div>
              <input
                type="text"
                class="input-field"
                v-model="gradeSearch"
                placeholder="Search grade..."
                @focus="gradeDropdownOpen = true"
                @input="gradeDropdownOpen = true"
                @blur="handleGradeBlur"
                autocomplete="off"
              />
              <ul v-if="gradeDropdownOpen && filteredGradeOptions.length > 0" class="dropdown-list">
                <li
                  v-for="option in filteredGradeOptions"
                  :key="option.value"
                  @mousedown.prevent="toggleGrade(option)"
                  :class="{ active: filters.grade.includes(option.value) }"
                >
                  <span class="grade-check">{{ filters.grade.includes(option.value) ? '✓' : '' }}</span>
                  {{ option.label }}
                </li>
              </ul>
            </div>
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
            <label>Width:</label>
            <select v-model="filters.width" class="input-field">
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
          <div class="filter-field">
            <label>Lot ID:</label>
            <input v-model="filters.lot_id" type="text" class="input-field" placeholder="Search By LotID" />
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

    <div class="card card-pad results-section" v-if="!isLoading && (rollLots.length > 0 || batchNotFound.length > 0)">
      <div class="results-info">
        <span v-if="filterMode === 'batch'">
          Found: <strong>{{ rollLots.length }}</strong> / {{ detectedLotIds.length }} LotIDs
        </span>
        <span v-else>
          Found: <strong>{{ totalItems }}</strong> items
        </span>
        <button
          v-if="rollLots.length > 0"
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
          <div class="skeleton-cell" v-for="m in 10" :key="'skel-c-'+m">
            <div class="skeleton-bar shimmer"></div>
          </div>
        </div>
      </div>

      <div class="table-wrapper" v-if="!isLoading && rollLots.length > 0">
      <table class="data-table">
        <thead>
          <tr>
            <th>LotID</th>
            <th>ItemID</th>
            <th class="col-weight">Weight</th>
            <th>RewID</th>
            <th>Papertype</th>
            <th>Gramature</th>
            <th>Width</th>
            <th>Grade</th>
            <th>Diameter</th>
            <th class="col-action">Action</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="lot in rollLots" :key="lot.id" class="row-hover">
            <td class="cell-lotid">{{ lot.lot_id }}</td>
            <td>{{ lot.item_id }}</td>
            <td class="cell-weight">
              <span :class="weightClass(lot.weight)">{{ lot.weight }}</span>
            </td>
            <td class="cell-muted">{{ lot.rew_id || '-' }}</td>
            <td>{{ lot.papertype }}</td>
            <td>{{ lot.gramature }}</td>
            <td>{{ lot.width }}</td>
            <td>
              <span v-if="lot.grade" :class="gradeClass(lot.grade)">{{ lot.grade }}</span>
              <span v-else class="cell-muted">-</span>
            </td>
            <td class="cell-muted">{{ lot.diameter ? lot.diameter + 'mm' : '-' }}</td>
            <td class="col-action">
              <button @click="showDetail(lot)" class="btn btn-icon-only" title="View details" aria-label="View details">
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
    <div v-if="!isLoading && rollLots.length === 0 && !batchNotFound.length" class="empty-state">
      <svg class="empty-icon-svg" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.5">
        <ellipse cx="12" cy="7" rx="8" ry="4"/>
        <path d="M20 7v4c0 2.2-3.6 4-8 4s-8-1.8-8-4V7"/>
        <path d="M4 11v4c0 2.2 3.6 4 8 4s8-1.8 8-4v-4"/>
      </svg>
      <h3>No Data Found</h3>
      <p>Upload a Mutasi Roll file first or adjust your filter criteria.</p>
      <router-link to="/upload" class="btn btn-primary">Go to Upload</router-link>
    </div>

    <!-- Detail Modal -->
    <DetailModal v-if="selectedLot" :lot="selectedLot" @close="selectedLot = null" />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import DetailModal from '../components/DetailModal.vue';

const filterMode = ref('advanced');
const batchLotIds = ref('');
const filters = ref({
  item_id: '',
  grade: [],
  papertype: '',
  gramature: '',
  width: '',
  date_from: '',
  date_to: '',
  lot_id: '',
});

// Distinct values for dropdown options
const distinctValues = ref({
  papertypes: [],
  gramatures: [],
  dimensions: [],
  item_ids: [],
});


const gradeSearch = ref('');
const gradeDropdownOpen = ref(false);

const filteredGradeOptions = computed(() => {
  const search = gradeSearch.value.toLowerCase();
  const options = [{ value: '', label: 'All' }];
  if (distinctValues.value.grades) {
    distinctValues.value.grades.forEach(g => {
      options.push({ value: g, label: g });
    });
  }
  if (!search) return options;
  return options.filter(opt => opt.label.toLowerCase().includes(search));
});

const selectGrade = (option) => {
  if (option.value === '') {
    filters.value.grade = [];
  } else {
    filters.value.grade = [option.value];
  }
  gradeSearch.value = option.value === '' ? '' : option.label;
  gradeDropdownOpen.value = false;
};

const toggleGrade = (option) => {
  if (option.value === '') {
    filters.value.grade = [];
  } else {
    const idx = filters.value.grade.indexOf(option.value);
    if (idx >= 0) {
      filters.value.grade.splice(idx, 1);
    } else {
      filters.value.grade.push(option.value);
    }
  }
  gradeSearch.value = '';
};

const removeGrade = (g) => {
  const idx = filters.value.grade.indexOf(g);
  if (idx >= 0) filters.value.grade.splice(idx, 1);
};

const handleGradeBlur = () => {
  setTimeout(() => {
    gradeDropdownOpen.value = false;
  }, 150);
};

const rollLots = ref([]);
const pagination = ref(null);
const currentPage = ref(1);
const selectedLot = ref(null);
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
    const response = await axios.get('/api/roll-lots/distinct-values');
    distinctValues.value = response.data;
  } catch (error) {
    console.error('Failed to fetch distinct values:', error);
  }
};

const searchBatch = async () => {
  isLoading.value = true;
  const lotIdsParam = detectedLotIds.value.join(',');
  try {
    const response = await axios.get('/api/roll-lots', {
      params: {
        mode: 'batch',
        lot_ids: lotIdsParam,
      },
    });
    rollLots.value = response.data.data;
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
      const val = filters.value[key];
      if (Array.isArray(val)) {
        if (val.length > 0) params[key] = val.join(',');
      } else if (val) {
        params[key] = val;
      }
    });
    const response = await axios.get('/api/roll-lots', { params });
    rollLots.value = response.data.data;
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
    grade: [],
    papertype: '',
    gramature: '',
    width: '',
    date_from: '',
    date_to: '',
    lot_id: '',
  };
  gradeSearch.value = '';
  currentPage.value = 1;
  searchAdvanced();
};

const exportData = async () => {
  try {
    const params = { resource: 'roll', mode: filterMode.value };
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

const showDetail = (lot) => {
  selectedLot.value = lot;
};

// Weight color classes
const weightClass = (w) => {
  const n = Number(w);
  if (n >= 1500) return 'weight-heavy';
  if (n >= 800) return 'weight-medium';
  return 'weight-light';
};

const gradeClass = (g) => {
  if (g === '1') return 'grade-badge grade-1';
  if (g === '2') return 'grade-badge grade-2';
  if (g === '3') return 'grade-badge grade-3';
  if (g === 'WIPB') return 'grade-badge grade-wipb';
  return 'grade-badge';
};

onMounted(() => {
  fetchDistinctValues();
  searchAdvanced();
});
</script>

<style scoped>
.home-page { padding: 0; }

/* Filter card spacing (card visuals come from global .card) */
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

/* Searchable select */
.searchable-select { position: relative; }
.grade-tags { display: flex; flex-wrap: wrap; gap: 0.35rem; margin-bottom: 0.4rem; }
.grade-tag {
  display: inline-flex; align-items: center; gap: 0.3rem;
  background: var(--primary-light); color: var(--primary); border: 1px solid var(--primary-soft);
  border-radius: var(--radius-sm); padding: 0.2rem 0.5rem; font-size: 0.8rem; font-weight: 500;
}
.grade-tag-remove {
  background: none; border: none; color: var(--primary); cursor: pointer;
  font-size: 1rem; line-height: 1; padding: 0; margin-left: 0.15rem;
}
.grade-tag-remove:hover { color: var(--danger); }
.grade-check { color: var(--primary); font-weight: 700; margin-right: 0.15rem; }
.dropdown-list {
  position: absolute;
  top: calc(100% + 2px);
  left: 0;
  right: 0;
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: var(--radius);
  box-shadow: var(--shadow-md);
  list-style: none;
  margin: 0;
  padding: 0.25rem 0;
  z-index: 100;
  max-height: 200px;
  overflow-y: auto;
}
.dropdown-list li { padding: 0.6rem 0.75rem; cursor: pointer; font-size: 0.9rem; color: var(--text-body); transition: background 0.15s; }
.dropdown-list li:hover, .dropdown-list li.active { background: var(--primary-light); color: var(--primary); }

/* Results */
.results-info {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
  font-size: 0.9rem;
  color: var(--text-body);
}
.data-table { min-width: 1000px; }
.col-weight { width: 100px; }
</style>
