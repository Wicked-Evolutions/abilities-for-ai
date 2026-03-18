<template>
  <div class="kl-view">
    <div class="kl-page-header">
      <h1 class="kl-page-title">Observations</h1>
      <span style="flex:1"></span>
    </div>

    <!-- Bulk Action Bar -->
    <div class="kl-bulk-bar" :class="{ visible: selectedIds.length > 0 }">
      <span class="kl-bulk-count">{{ selectedIds.length }} selected</span>
      <button class="kl-btn kl-btn-sm kl-btn-success" @click="bulkResolve">Resolve</button>
      <button class="kl-btn kl-btn-sm kl-btn-secondary" @click="bulkDefer">Defer</button>
      <button class="kl-btn kl-btn-sm kl-btn-secondary" @click="bulkWontFix">Won't Fix</button>
    </div>

    <div class="kl-toolbar">
      <select class="kl-filter" v-model="store.filters.status" @change="applyFilters">
        <option value="">All Statuses</option>
        <option value="open">open</option>
        <option value="resolved">resolved</option>
        <option value="deferred">deferred</option>
        <option value="wont_fix">wont_fix</option>
      </select>
      <select class="kl-filter" v-model="store.filters.category" @change="applyFilters">
        <option value="">All Categories</option>
        <option value="technical">technical</option>
        <option value="strategic">strategic</option>
        <option value="security">security</option>
        <option value="content">content</option>
        <option value="design">design</option>
      </select>
      <select class="kl-filter" v-model="store.filters.severity" @change="applyFilters">
        <option value="">All Severities</option>
        <option value="action_needed">action_needed</option>
        <option value="attention">attention</option>
        <option value="info">info</option>
      </select>
    </div>

    <div class="kl-table-wrap" v-loading="store.loading">
      <table class="kl-table">
        <thead>
          <tr>
            <th class="col-check"><input type="checkbox" :checked="allSelected" @change="toggleAll" /></th>
            <th>Severity</th>
            <th>Category</th>
            <th>Description</th>
            <th>Status</th>
            <th>Session</th>
            <th>Created</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="obs in store.items"
            :key="obs.id"
            :style="{ opacity: isResolved(obs) ? 0.6 : 1 }"
          >
            <td class="col-check" @click.stop>
              <input type="checkbox" :checked="selectedIds.includes(obs.id)" @change="toggleSelect(obs.id)" />
            </td>
            <td><span class="kl-badge" :class="`badge-${obs.severity || 'info'}`">{{ obs.severity || 'info' }}</span></td>
            <td><span class="kl-badge badge-agent-type">{{ obs.category || 'general' }}</span></td>
            <td>{{ obs.title || obs.content }}</td>
            <td><span class="kl-badge" :class="`badge-${obs.status || 'open'}`">{{ obs.status || 'open' }}</span></td>
            <td class="kl-cell-mono">#{{ (obs.session_id || '').toString().substring(0, 6) }}</td>
            <td class="kl-cell-mono kl-cell-muted">{{ formatDate(obs.created_at) }}</td>
            <td>
              <div v-if="obs.status === 'open'" style="display:flex; gap:6px;">
                <button class="kl-btn kl-btn-sm kl-btn-success" @click="openResolve(obs)">Resolve</button>
                <button class="kl-btn kl-btn-sm kl-btn-ghost" @click="deferObs(obs)">Defer</button>
              </div>
              <div v-else-if="obs.status === 'resolved'">
                <span style="font-family:var(--font-mono); font-size:.75rem; color:var(--status-active);">&#10003; {{ formatDate(obs.resolved_at) }}</span>
              </div>
              <div v-else>
                <span style="font-family:var(--font-mono); font-size:.75rem; color:var(--text-muted);">— {{ formatDate(obs.resolved_at || obs.updated_at) }}</span>
              </div>
            </td>
          </tr>
          <tr v-if="!store.loading && store.items.length === 0">
            <td colspan="8" style="text-align:center; padding:40px; color:var(--text-muted);">No observations found.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <Pagination
      :page="store.page"
      :per-page="store.perPage"
      :total="store.total"
      @update:page="changePage"
    />

    <!-- Resolve Dialog -->
    <el-dialog v-model="showResolve" title="Resolve Observation" width="480px">
      <p style="margin-bottom:12px; color:var(--text-secondary);">{{ resolveTarget?.title || resolveTarget?.content }}</p>
      <el-input
        v-model="resolveNote"
        type="textarea"
        :rows="3"
        placeholder="Resolution note (optional)..."
      />
      <template #footer>
        <el-button @click="showResolve = false">Cancel</el-button>
        <el-button type="primary" @click="confirmResolve">Resolve</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useObservationsStore } from '../stores/observations.js'
import Pagination from '../components/Pagination.vue'

const store = useObservationsStore()
const selectedIds = ref([])
const showResolve = ref(false)
const resolveTarget = ref(null)
const resolveNote = ref('')

const allSelected = computed(() =>
  store.items.length > 0 && store.items.every(o => selectedIds.value.includes(o.id))
)

function isResolved(obs) {
  return obs.status === 'resolved' || obs.status === 'wont_fix'
}

function applyFilters() {
  store.page = 1
  store.fetchObservations()
}

function changePage(p) {
  store.setPage(p)
  store.fetchObservations()
}

function toggleSelect(id) {
  const idx = selectedIds.value.indexOf(id)
  if (idx > -1) selectedIds.value.splice(idx, 1)
  else selectedIds.value.push(id)
}

function toggleAll() {
  if (allSelected.value) selectedIds.value = []
  else selectedIds.value = store.items.map(o => o.id)
}

function openResolve(obs) {
  resolveTarget.value = obs
  resolveNote.value = ''
  showResolve.value = true
}

async function confirmResolve() {
  await store.resolveObservation(resolveTarget.value.id, resolveNote.value)
  showResolve.value = false
  selectedIds.value = []
}

async function deferObs(obs) {
  await store.bulkAction('defer', [obs.id])
}

async function bulkResolve() {
  await store.bulkAction('resolve', selectedIds.value)
  selectedIds.value = []
}

async function bulkDefer() {
  await store.bulkAction('defer', selectedIds.value)
  selectedIds.value = []
}

async function bulkWontFix() {
  await store.bulkAction('wont_fix', selectedIds.value)
  selectedIds.value = []
}

function formatDate(dateStr) {
  if (!dateStr) return '—'
  const d = new Date(dateStr)
  return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
}

onMounted(() => store.fetchObservations())
</script>
