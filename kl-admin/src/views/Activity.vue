<template>
  <div class="kl-view">
    <div class="kl-page-header">
      <h1 class="kl-page-title">Activity</h1>
      <span style="flex:1"></span>
      <span style="font-family:var(--font-mono); font-size:.75rem; color:var(--text-muted);">Automatic · Append-only execution log</span>
    </div>

    <div class="kl-toolbar">
      <input
        class="kl-filter kl-search-input"
        type="text"
        placeholder="Search ability name..."
        v-model="searchQuery"
        @input="onSearch"
      />
      <select class="kl-filter" v-model="store.filters.category" @change="applyFilters">
        <option value="">All Categories</option>
        <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
      </select>
      <select class="kl-filter" v-model="store.filters.status" @change="applyFilters">
        <option value="">All Statuses</option>
        <option value="success">success</option>
        <option value="error">error</option>
      </select>
      <select class="kl-filter" v-model="store.filters.date_range" @change="applyFilters">
        <option value="">All Time</option>
        <option value="1">Last 24 hours</option>
        <option value="7">Last 7 days</option>
        <option value="30">Last 30 days</option>
        <option value="90">Last 90 days</option>
      </select>
    </div>

    <div class="kl-table-wrap" v-loading="store.loading">
      <table class="kl-table">
        <thead>
          <tr>
            <th>Ability</th>
            <th>Category</th>
            <th>Status</th>
            <th>Duration</th>
            <th>User</th>
            <th>Session</th>
            <th>Time</th>
          </tr>
        </thead>
        <tbody>
          <template v-for="a in store.items" :key="a.id">
            <tr @click="toggleExpand(a.id)" :class="{ 'kl-row-error': a.status === 'error' }">
              <td class="kl-cell-mono">{{ a.ability_name }}</td>
              <td><span class="kl-badge badge-agent-type">{{ a.category || '—' }}</span></td>
              <td><StatusBadge :value="a.status" /></td>
              <td class="kl-cell-mono kl-cell-muted">{{ a.duration_ms }}ms</td>
              <td class="kl-cell-mono kl-cell-muted">{{ a.user_id || '—' }}</td>
              <td class="kl-cell-mono kl-cell-muted">{{ a.session_id ? '#' + a.session_id.substring(0, 6) : '—' }}</td>
              <td class="kl-cell-mono kl-cell-muted">{{ formatDateTime(a.created_at) }}</td>
            </tr>
            <tr v-if="expandedId === a.id" class="kl-expand-row">
              <td colspan="7">
                <div class="kl-expand-content">
                  <div class="kl-detail-grid">
                    <div>
                      <strong>Ability Name</strong>
                      <span class="kl-cell-mono">{{ a.ability_name }}</span>
                    </div>
                    <div>
                      <strong>Category</strong>
                      {{ a.category || '—' }}
                    </div>
                    <div>
                      <strong>Status</strong>
                      <StatusBadge :value="a.status" />
                    </div>
                    <div>
                      <strong>Duration</strong>
                      <span class="kl-cell-mono">{{ a.duration_ms }}ms</span>
                    </div>
                    <div>
                      <strong>User ID</strong>
                      <span class="kl-cell-mono">{{ a.user_id || 'system' }}</span>
                    </div>
                    <div>
                      <strong>Session ID</strong>
                      <span class="kl-cell-mono">{{ a.session_id || '—' }}</span>
                    </div>
                    <div>
                      <strong>Input Hash</strong>
                      <span class="kl-cell-mono">{{ a.input_hash || '—' }}</span>
                    </div>
                    <div v-if="a.error_code">
                      <strong>Error Code</strong>
                      <span class="kl-cell-mono" style="color:var(--sev-action);">{{ a.error_code }}</span>
                    </div>
                    <div>
                      <strong>Timestamp</strong>
                      <span class="kl-cell-mono">{{ a.created_at }}</span>
                    </div>
                  </div>
                </div>
              </td>
            </tr>
          </template>
          <tr v-if="!store.loading && store.items.length === 0">
            <td colspan="7" style="text-align:center; padding:40px; color:var(--text-muted);">No activity records found.</td>
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
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useActivityStore } from '../stores/index.js'
import Pagination from '../components/Pagination.vue'
import StatusBadge from '../components/StatusBadge.vue'

const store = useActivityStore()
const expandedId = ref(null)
const searchQuery = ref('')
let searchTimeout = null

const categories = [
  'blocks', 'cache', 'comments', 'content', 'cron', 'diagnostic',
  'editorial', 'filesystem', 'knowledge', 'media', 'menus', 'meta',
  'multisite', 'patterns', 'plugins', 'rest', 'revisions', 'rewrite',
  'settings', 'site-health', 'status', 'taxonomies', 'themes', 'users',
]

function toggleExpand(id) {
  expandedId.value = expandedId.value === id ? null : id
}

function onSearch() {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    store.setFilter('ability_name', searchQuery.value)
    store.fetchActivity()
  }, 400)
}

function applyFilters() {
  store.page = 1
  store.fetchActivity()
}

function changePage(p) {
  store.setPage(p)
  store.fetchActivity()
}

function formatDateTime(dateStr) {
  if (!dateStr) return '—'
  const d = new Date(dateStr)
  return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) + ', ' +
    d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: false })
}

onMounted(() => store.fetchActivity())
</script>

<style scoped>
.kl-search-input {
  min-width: 200px;
  padding: 6px 10px;
  border: 1px solid var(--border);
  border-radius: 6px;
  background: var(--bg-card);
  color: var(--text-primary);
  font-family: var(--font-mono);
  font-size: .8125rem;
}
.kl-search-input::placeholder {
  color: var(--text-muted);
}
.kl-row-error {
  background: rgba(var(--accent-error-rgb, 239, 68, 68), 0.05);
}
.kl-detail-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 12px 24px;
}
.kl-detail-grid strong {
  display: block;
  font-size: .6875rem;
  text-transform: uppercase;
  letter-spacing: .05em;
  color: var(--text-muted);
  margin-bottom: 2px;
}
</style>
