<template>
  <div class="kl-view">
    <div class="kl-page-header">
      <h1 class="kl-page-title">Activity</h1>
      <span style="flex:1"></span>
      <span style="font-family:var(--font-mono); font-size:.75rem; color:var(--text-muted);">Automatic · Append-only execution log</span>
    </div>

    <div class="kl-tab-row">
      <label class="kl-tab-radio" :class="{ active: viewMode === 'activity' }">
        <input type="radio" v-model="viewMode" value="activity" @change="onViewChange" />
        <span class="kl-tab-bullet"></span>
        Ability executions
      </label>
      <label class="kl-tab-radio" :class="{ active: viewMode === 'boundary' }">
        <input type="radio" v-model="viewMode" value="boundary" @change="onViewChange" />
        <span class="kl-tab-bullet"></span>
        Boundary events
      </label>
      <label class="kl-tab-radio" :class="{ active: viewMode === 'both' }">
        <input type="radio" v-model="viewMode" value="both" @change="onViewChange" />
        <span class="kl-tab-bullet"></span>
        Both
      </label>
    </div>

    <!-- Ability executions view (existing kl_activity behaviour). -->
    <template v-if="viewMode === 'activity'">
      <div class="kl-toolbar">
        <input
          class="kl-filter kl-search-input"
          type="text"
          placeholder="Search ability name..."
          v-model="searchQuery"
          @input="onSearch"
        />
        <select class="kl-filter" v-model="activityStore.filters.category" @change="applyActivityFilters">
          <option value="">All Categories</option>
          <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
        </select>
        <select class="kl-filter" v-model="activityStore.filters.status" @change="applyActivityFilters">
          <option value="">All Statuses</option>
          <option value="success">success</option>
          <option value="error">error</option>
        </select>
        <select class="kl-filter" v-model="activityStore.filters.caller_origin" @change="applyActivityFilters" title="Caller origin">
          <option value="">All Callers</option>
          <option value="mcp">MCP</option>
          <option value="rest">REST</option>
          <option value="wp-admin">wp-admin</option>
          <option value="wp-cron">wp-cron</option>
          <option value="cli">CLI</option>
          <option value="internal">internal</option>
        </select>
        <select class="kl-filter" v-model="activityStore.filters.is_compiled" @change="applyActivityFilters" title="Compiled vs CRUD">
          <option value="">All Types</option>
          <option value="true">Compiled only</option>
          <option value="false">CRUD only</option>
        </select>
        <select class="kl-filter" v-model="activityStore.filters.date_range" @change="applyActivityFilters">
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

      <div class="kl-table-wrap" v-loading="activityStore.loading">
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
            <template v-for="a in activityStore.items" :key="a.id">
              <tr @click="toggleExpand('a-' + a.id)" :class="{ 'kl-row-error': a.status === 'error' }">
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
              <tr v-if="expandedId === 'a-' + a.id" class="kl-expand-row">
                <td colspan="9">
                  <div class="kl-expand-content">
                    <div class="kl-detail-grid">
                      <div><strong>Ability Name</strong><span class="kl-cell-mono">{{ a.ability_name }}</span></div>
                      <div><strong>Category</strong>{{ a.category || '—' }}</div>
                      <div><strong>Status</strong><StatusBadge :value="a.status" /></div>
                      <div><strong>Caller Origin</strong><span class="kl-cell-mono">{{ a.caller_origin || '—' }}</span></div>
                      <div><strong>Compiled</strong>{{ a.is_compiled ? 'yes — crosses plugin boundaries' : 'no — single domain CRUD' }}</div>
                      <div><strong>Replaces</strong><span class="kl-cell-mono">{{ a.replaced_surface || '—' }}</span></div>
                      <div><strong>Duration</strong><span class="kl-cell-mono">{{ a.duration_ms }}ms</span></div>
                      <div><strong>Response Size</strong><span class="kl-cell-mono">{{ formatBytes(a.response_size_bytes) }}</span></div>
                      <div><strong>Input Size</strong><span class="kl-cell-mono">{{ formatBytes(a.input_size_bytes) }}</span></div>
                      <div><strong>Memory Delta</strong><span class="kl-cell-mono">{{ formatBytes(a.memory_delta_bytes) }}</span></div>
                      <div><strong>SQL Queries</strong><span class="kl-cell-mono">{{ a.sql_query_count || 0 }}</span></div>
                      <div><strong>User ID</strong><span class="kl-cell-mono">{{ a.user_id || 'system' }}</span></div>
                      <div><strong>Session ID</strong><span class="kl-cell-mono">{{ a.session_id || '—' }}</span></div>
                      <div><strong>Input Hash</strong><span class="kl-cell-mono">{{ a.input_hash || '—' }}</span></div>
                      <div><strong>Response Hash</strong><span class="kl-cell-mono">{{ a.response_hash || '—' }}</span></div>
                      <div v-if="a.error_code"><strong>Error Code</strong><span class="kl-cell-mono" style="color:var(--sev-action);">{{ a.error_code }}</span></div>
                      <div><strong>Timestamp</strong><span class="kl-cell-mono">{{ a.created_at }}</span></div>
                    </div>
                  </div>
                </td>
              </tr>
            </template>
            <tr v-if="!activityStore.loading && activityStore.items.length === 0">
              <td colspan="9" style="text-align:center; padding:40px; color:var(--text-muted);">No activity records found.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <Pagination
        :page="activityStore.page"
        :per-page="activityStore.perPage"
        :total="activityStore.total"
        @update:page="changeActivityPage"
      />
    </template>

    <!-- Boundary events view (kl_boundary). -->
    <template v-else-if="viewMode === 'boundary'">
      <div class="kl-toolbar">
        <select class="kl-filter" v-model="boundaryStore.filters.event" @change="applyBoundaryFilters">
          <option value="">All Events</option>
          <option value="boundary.session.init">session.init</option>
          <option value="boundary.session.terminated">session.terminated</option>
          <option value="boundary.auth.denied">auth.denied</option>
          <option value="boundary.transport.error">transport.error</option>
          <option value="boundary.rate_limit_hit">rate_limit_hit</option>
        </select>
        <select class="kl-filter" v-model="boundaryStore.filters.severity" @change="applyBoundaryFilters">
          <option value="">All Severities</option>
          <option value="info">info</option>
          <option value="warn">warn</option>
          <option value="error">error</option>
          <option value="critical">critical</option>
        </select>
        <input
          class="kl-filter kl-search-input"
          type="text"
          placeholder="Filter by session id..."
          v-model="boundarySession"
          @input="onBoundarySessionInput"
        />
        <select class="kl-filter" v-model="boundaryStore.filters.date_range" @change="applyBoundaryFilters">
          <option value="">All Time</option>
          <option value="1">Last 24 hours</option>
          <option value="7">Last 7 days</option>
          <option value="30">Last 30 days</option>
          <option value="90">Last 90 days</option>
        </select>
      </div>

      <div class="kl-table-wrap" v-loading="boundaryStore.loading">
        <table class="kl-table">
          <thead>
            <tr>
              <th>Event</th>
              <th>Severity</th>
              <th>Method</th>
              <th>Status</th>
              <th>Error</th>
              <th>Client</th>
              <th>IP</th>
              <th>Duration</th>
              <th>Time</th>
            </tr>
          </thead>
          <tbody>
            <template v-for="b in boundaryStore.items" :key="b.id">
              <tr @click="toggleExpand('b-' + b.id)" :class="{ 'kl-row-error': b.severity === 'warn' || b.severity === 'error' || b.severity === 'critical' }">
                <td class="kl-cell-mono">{{ b.event }}</td>
                <td><span class="kl-badge" :class="severityClass(b.severity)">{{ b.severity }}</span></td>
                <td class="kl-cell-mono kl-cell-muted" style="font-size:.7rem;">{{ b.method || '—' }}</td>
                <td class="kl-cell-mono kl-cell-muted">{{ b.status_code || '—' }}</td>
                <td class="kl-cell-mono kl-cell-muted" style="font-size:.7rem;">{{ b.error_code || '—' }}</td>
                <td class="kl-cell-mono kl-cell-muted" style="font-size:.7rem;">{{ b.client_name || '—' }}</td>
                <td class="kl-cell-mono kl-cell-muted" style="font-size:.7rem;">{{ b.ip_truncated || '—' }}</td>
                <td class="kl-cell-mono kl-cell-muted">{{ b.duration_ms ? b.duration_ms + 'ms' : '—' }}</td>
                <td class="kl-cell-mono kl-cell-muted">{{ formatDateTime(b.created_at) }}</td>
              </tr>
              <tr v-if="expandedId === 'b-' + b.id" class="kl-expand-row">
                <td colspan="9">
                  <div class="kl-expand-content">
                    <div class="kl-detail-grid">
                      <div><strong>Event</strong><span class="kl-cell-mono">{{ b.event }}</span></div>
                      <div><strong>Severity</strong><span class="kl-cell-mono">{{ b.severity }}</span></div>
                      <div><strong>Method</strong><span class="kl-cell-mono">{{ b.method || '—' }}</span></div>
                      <div><strong>Request ID</strong><span class="kl-cell-mono">{{ b.request_id || '—' }}</span></div>
                      <div><strong>Transport</strong><span class="kl-cell-mono">{{ b.transport || '—' }}</span></div>
                      <div><strong>Status Code</strong><span class="kl-cell-mono">{{ b.status_code || '—' }}</span></div>
                      <div><strong>Error Code</strong><span class="kl-cell-mono" style="color:var(--sev-action);">{{ b.error_code || '—' }}</span></div>
                      <div><strong>User ID</strong><span class="kl-cell-mono">{{ b.user_id || '—' }}</span></div>
                      <div><strong>Session ID</strong><span class="kl-cell-mono">{{ b.session_id || '—' }}</span></div>
                      <div><strong>API Key Hash</strong><span class="kl-cell-mono">{{ b.api_key_hash || '—' }}</span></div>
                      <div><strong>Client</strong><span class="kl-cell-mono">{{ b.client_name || '—' }}</span></div>
                      <div><strong>User Agent</strong><span class="kl-cell-mono">{{ b.user_agent || '—' }}</span></div>
                      <div><strong>IP (truncated)</strong><span class="kl-cell-mono">{{ b.ip_truncated || '—' }}</span></div>
                      <div><strong>Duration</strong><span class="kl-cell-mono">{{ b.duration_ms ? b.duration_ms + 'ms' : '—' }}</span></div>
                      <div style="grid-column: 1 / -1;"><strong>Detail</strong><pre class="kl-cell-mono" style="white-space:pre-wrap; margin:0;">{{ b.detail_json || '—' }}</pre></div>
                      <div><strong>Timestamp</strong><span class="kl-cell-mono">{{ b.created_at }}</span></div>
                    </div>
                  </div>
                </td>
              </tr>
            </template>
            <tr v-if="!boundaryStore.loading && boundaryStore.items.length === 0">
              <td colspan="9" style="text-align:center; padding:40px; color:var(--text-muted);">No boundary events found.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <Pagination
        :page="boundaryStore.page"
        :per-page="boundaryStore.perPage"
        :total="boundaryStore.total"
        @update:page="changeBoundaryPage"
      />
    </template>

    <!-- Both — UNION timeline view via /timeline route. -->
    <template v-else>
      <div class="kl-table-wrap" v-loading="timelineLoading">
        <table class="kl-table">
          <thead>
            <tr>
              <th>Kind</th>
              <th>Event / Ability</th>
              <th>Severity / Status</th>
              <th>Duration</th>
              <th>Session</th>
              <th>IP</th>
              <th>Time</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in timelineItems" :key="row.kind + '-' + row.id">
              <td>
                <span class="kl-badge" :class="row.kind === 'boundary' ? 'kl-badge-boundary' : 'kl-badge-activity'">
                  {{ row.kind }}
                </span>
              </td>
              <td class="kl-cell-mono">{{ row.label }}</td>
              <td>
                <span class="kl-badge" :class="row.kind === 'boundary' ? severityClass(row.severity) : ''">
                  {{ row.severity || row.status }}
                </span>
              </td>
              <td class="kl-cell-mono kl-cell-muted">{{ row.duration_ms || 0 }}ms</td>
              <td class="kl-cell-mono kl-cell-muted" style="font-size:.7rem;">{{ row.session_id || '—' }}</td>
              <td class="kl-cell-mono kl-cell-muted" style="font-size:.7rem;">{{ row.ip_truncated || '—' }}</td>
              <td class="kl-cell-mono kl-cell-muted">{{ formatDateTime(row.created_at) }}</td>
            </tr>
            <tr v-if="!timelineLoading && timelineItems.length === 0">
              <td colspan="7" style="text-align:center; padding:40px; color:var(--text-muted);">No timeline records found.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <Pagination
        :page="timelinePage"
        :per-page="timelinePerPage"
        :total="timelineTotal"
        @update:page="changeTimelinePage"
      />
    </template>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useActivityStore, useBoundaryStore } from '../stores/index.js'
import { api } from '../api.js'
import Pagination from '../components/Pagination.vue'
import StatusBadge from '../components/StatusBadge.vue'

const activityStore = useActivityStore()
const boundaryStore = useBoundaryStore()
const expandedId = ref(null)
const searchQuery = ref('')
const sortKey = ref('created_at')
const viewMode = ref('activity')
const boundarySession = ref('')
let searchTimeout = null
let boundarySessionTimeout = null

// Timeline (UNION) view state.
const timelineItems = ref([])
const timelineTotal = ref(0)
const timelinePage = ref(1)
const timelinePerPage = ref(20)
const timelineLoading = ref(false)

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
    activityStore.setFilter('ability_name', searchQuery.value)
    activityStore.fetchActivity()
  }, 400)
}

function onBoundarySessionInput() {
  clearTimeout(boundarySessionTimeout)
  boundarySessionTimeout = setTimeout(() => {
    boundaryStore.setFilter('session_id', boundarySession.value)
    boundaryStore.fetchBoundary()
  }, 400)
}

function applyActivityFilters() {
  activityStore.page = 1
  activityStore.fetchActivity()
}

function applyBoundaryFilters() {
  boundaryStore.page = 1
  boundaryStore.fetchBoundary()
}

function applySort() {
  const order = sortKey.value === 'created_at' ? 'DESC' : 'DESC'
  activityStore.setSort(sortKey.value, order)
  activityStore.fetchActivity()
}

function changeActivityPage(p) {
  activityStore.setPage(p)
  activityStore.fetchActivity()
}

function changeBoundaryPage(p) {
  boundaryStore.setPage(p)
  boundaryStore.fetchBoundary()
}

function changeTimelinePage(p) {
  timelinePage.value = p
  fetchTimeline()
}

async function fetchTimeline() {
  timelineLoading.value = true
  try {
    const params = {
      view: 'both',
      page: timelinePage.value,
      per_page: timelinePerPage.value,
    }
    const data = await api.get('timeline', params)
    timelineItems.value = Array.isArray(data) ? data : (data.items || [])
    timelineTotal.value = Array.isArray(data) ? data.length : (data.total || 0)
  } catch (e) {
    timelineItems.value = []
  } finally {
    timelineLoading.value = false
  }
}

function onViewChange() {
  expandedId.value = null
  if (viewMode.value === 'activity' && activityStore.items.length === 0) {
    activityStore.fetchActivity()
  } else if (viewMode.value === 'boundary' && boundaryStore.items.length === 0) {
    boundaryStore.fetchBoundary()
  } else if (viewMode.value === 'both') {
    fetchTimeline()
  }
}

function severityClass(sev) {
  if (sev === 'critical' || sev === 'error') return 'kl-badge-sev-error'
  if (sev === 'warn') return 'kl-badge-sev-warn'
  return 'kl-badge-sev-info'
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

onMounted(() => activityStore.fetchActivity())
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
.kl-tab-row {
  display: flex;
  gap: 4px;
  margin: 12px 0 16px;
  padding: 4px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 8px;
  width: fit-content;
}
.kl-tab-radio {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  border-radius: 6px;
  cursor: pointer;
  font-size: .8125rem;
  color: var(--text-muted);
  transition: background .15s, color .15s;
}
.kl-tab-radio.active {
  background: var(--bg-elevated, rgba(255,255,255,0.06));
  color: var(--text-primary);
}
.kl-tab-radio input[type="radio"] {
  display: none;
}
.kl-tab-bullet {
  display: inline-block;
  width: 8px;
  height: 8px;
  border-radius: 50%;
  border: 1.5px solid currentColor;
  background: transparent;
}
.kl-tab-radio.active .kl-tab-bullet {
  background: currentColor;
}
.kl-badge-boundary {
  background: rgba(168,85,247,0.12);
  color: #a855f7;
  font-size: .6875rem;
  text-transform: uppercase;
  letter-spacing: .05em;
  padding: 2px 6px;
  border-radius: 3px;
  font-family: var(--font-mono);
}
.kl-badge-activity {
  background: rgba(59,130,246,0.12);
  color: #3b82f6;
  font-size: .6875rem;
  text-transform: uppercase;
  letter-spacing: .05em;
  padding: 2px 6px;
  border-radius: 3px;
  font-family: var(--font-mono);
}
.kl-badge-sev-info {
  background: rgba(59,130,246,0.12);
  color: #3b82f6;
}
.kl-badge-sev-warn {
  background: rgba(245,158,11,0.14);
  color: #f59e0b;
}
.kl-badge-sev-error {
  background: rgba(239,68,68,0.14);
  color: #ef4444;
}
</style>
