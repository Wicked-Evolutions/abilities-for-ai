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
      <select class="kl-filter" v-model="store.filters.caller_origin" @change="applyFilters" title="Caller origin">
        <option value="">All Callers</option>
        <option value="mcp">MCP</option>
        <option value="rest">REST</option>
        <option value="wp-admin">wp-admin</option>
        <option value="wp-cron">wp-cron</option>
        <option value="cli">CLI</option>
        <option value="internal">internal</option>
      </select>
      <select class="kl-filter" v-model="store.filters.is_compiled" @change="applyFilters" title="Compiled vs CRUD">
        <option value="">All Types</option>
        <option value="true">Compiled only</option>
        <option value="false">CRUD only</option>
      </select>
      <select class="kl-filter" v-model="store.filters.date_range" @change="applyFilters">
        <option value="">All Time</option>
        <option value="1">Last 24 hours</option>
        <option value="7">Last 7 days</option>
        <option value="30">Last 30 days</option>
        <option value="90">Last 90 days</option>
      </select>
      <select class="kl-filter" v-model="sortKey" @change="applySort" title="Sort by">
        <option value="created_at">Newest</option>
        <option value="duration_ms">Slowest</option>
        <option value="response_size_bytes">Largest response</option>
        <option value="memory_delta_bytes">Most memory</option>
        <option value="sql_query_count">Most queries</option>
        <option value="input_size_bytes">Largest input</option>
      </select>
    </div>

    <div class="kl-table-wrap" v-loading="store.loading">
      <table class="kl-table">
        <thead>
          <tr>
            <th>Ability</th>
            <th>Category</th>
            <th>Caller</th>
            <th>Compiled</th>
            <th>Status</th>
            <th>Duration</th>
            <th>Response</th>
            <th>Queries</th>
            <th>Time</th>
          </tr>
        </thead>
        <tbody>
          <template v-for="a in store.items" :key="a.id">
            <tr @click="toggleExpand(a.id)" :class="{ 'kl-row-error': a.status === 'error' }">
              <td class="kl-cell-mono">{{ a.ability_name }}</td>
              <td><span class="kl-badge badge-agent-type">{{ a.category || '—' }}</span></td>
              <td class="kl-cell-mono kl-cell-muted" style="font-size:.7rem;">{{ a.caller_origin || '—' }}</td>
              <td>
                <span v-if="a.is_compiled" class="kl-badge kl-badge-compiled">compiled</span>
                <span v-else class="kl-cell-muted" style="font-size:.7rem;">crud</span>
              </td>
              <td><StatusBadge :value="a.status" /></td>
              <td class="kl-cell-mono kl-cell-muted">{{ a.duration_ms }}ms</td>
              <td class="kl-cell-mono kl-cell-muted">{{ formatBytes(a.response_size_bytes) }}</td>
              <td class="kl-cell-mono kl-cell-muted">{{ a.sql_query_count || 0 }}</td>
              <td class="kl-cell-mono kl-cell-muted">{{ formatDateTime(a.created_at) }}</td>
            </tr>
            <tr v-if="expandedId === a.id" class="kl-expand-row">
              <td colspan="9">
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
                      <strong>Caller Origin</strong>
                      <span class="kl-cell-mono">{{ a.caller_origin || '—' }}</span>
                    </div>
                    <div>
                      <strong>Compiled</strong>
                      <span>{{ a.is_compiled ? 'yes — crosses plugin boundaries' : 'no — single domain CRUD' }}</span>
                    </div>
                    <div>
                      <strong>Replaces</strong>
                      <span class="kl-cell-mono">{{ a.replaced_surface || '—' }}</span>
                    </div>
                    <div>
                      <strong>Duration</strong>
                      <span class="kl-cell-mono">{{ a.duration_ms }}ms</span>
                    </div>
                    <div>
                      <strong>Response Size</strong>
                      <span class="kl-cell-mono">{{ formatBytes(a.response_size_bytes) }}</span>
                    </div>
                    <div>
                      <strong>Input Size</strong>
                      <span class="kl-cell-mono">{{ formatBytes(a.input_size_bytes) }}</span>
                    </div>
                    <div>
                      <strong>Memory Delta</strong>
                      <span class="kl-cell-mono">{{ formatBytes(a.memory_delta_bytes) }}</span>
                    </div>
                    <div>
                      <strong>SQL Queries</strong>
                      <span class="kl-cell-mono">{{ a.sql_query_count || 0 }}</span>
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
                    <div>
                      <strong>Response Hash</strong>
                      <span class="kl-cell-mono">{{ a.response_hash || '—' }}</span>
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
            <td colspan="9" style="text-align:center; padding:40px; color:var(--text-muted);">No activity records found.</td>
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
const sortKey = ref('created_at')
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

function applySort() {
  // For non-chronological sort, default to DESC (slowest, largest first).
  const order = sortKey.value === 'created_at' ? 'DESC' : 'DESC'
  store.setSort(sortKey.value, order)
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

function formatBytes(n) {
  if (!n) return '0 B'
  const abs = Math.abs(n)
  if (abs < 1024) return `${n} B`
  if (abs < 1024 * 1024) return `${(n / 1024).toFixed(1)} KB`
  if (abs < 1024 * 1024 * 1024) return `${(n / 1024 / 1024).toFixed(1)} MB`
  return `${(n / 1024 / 1024 / 1024).toFixed(2)} GB`
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
.kl-badge-compiled {
  background: var(--accent-success, rgba(34,197,94,0.15));
  color: var(--accent-success-fg, #22c55e);
  font-size: .6875rem;
  text-transform: uppercase;
  letter-spacing: .05em;
  padding: 2px 6px;
  border-radius: 3px;
  font-family: var(--font-mono);
}
</style>
