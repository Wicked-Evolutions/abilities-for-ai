<template>
  <div class="kl-view">
    <div class="kl-page-header">
      <h1 class="kl-page-title">Sessions</h1>
      <span style="flex:1"></span>
      <span style="font-family:var(--font-mono); font-size:.75rem; color:var(--text-muted);">Append-only · Read-only log</span>
    </div>

    <div class="kl-toolbar">
      <select class="kl-filter" v-model="store.filters.agent_type" @change="applyFilters">
        <option value="">All Agent Types</option>
        <option value="diagnostician">diagnostician</option>
        <option value="publisher">publisher</option>
        <option value="designer">designer</option>
        <option value="maintainer">maintainer</option>
        <option value="operator">operator</option>
      </select>
      <select class="kl-filter" v-model="store.filters.date_range" @change="applyFilters">
        <option value="">All Time</option>
        <option value="7d">Last 7 days</option>
        <option value="30d">Last 30 days</option>
        <option value="90d">Last 90 days</option>
      </select>
    </div>

    <div class="kl-table-wrap" v-loading="store.loading">
      <table class="kl-table">
        <thead>
          <tr>
            <th>Session ID</th>
            <th>Agent</th>
            <th>Model</th>
            <th>Started</th>
            <th>Duration</th>
            <th>Summary</th>
            <th>Obs.</th>
          </tr>
        </thead>
        <tbody>
          <template v-for="s in store.items" :key="s.id">
            <tr @click="toggleExpand(s.id)">
              <td class="kl-cell-mono">#{{ (s.session_id || s.id || '').toString().substring(0, 6) }}</td>
              <td><span class="kl-badge badge-agent-type">{{ s.agent_type || 'unknown' }}</span></td>
              <td class="kl-cell-mono kl-cell-muted">{{ s.model || '—' }}</td>
              <td class="kl-cell-mono kl-cell-muted">{{ formatDateTime(s.started_at || s.created_at) }}</td>
              <td class="kl-cell-mono kl-cell-muted">{{ formatDuration(s.duration_minutes) }}</td>
              <td style="max-width:300px;">{{ truncate(s.summary, 80) }}</td>
              <td class="kl-cell-mono">{{ s.observation_count || 0 }}</td>
            </tr>
            <tr v-if="expandedId === s.id" class="kl-expand-row">
              <td colspan="7">
                <div class="kl-expand-content">
                  <strong>Summary</strong>
                  {{ s.summary || 'No summary available.' }}
                  <template v-if="s.protocols_run">
                    <strong>Protocols Run</strong>
                    {{ Array.isArray(s.protocols_run) ? s.protocols_run.join(', ') : s.protocols_run }}
                  </template>
                  <template v-if="s.documents_modified">
                    <strong>Documents Modified</strong>
                    {{ Array.isArray(s.documents_modified) ? s.documents_modified.map(d => typeof d === 'object' ? d.title : d).join(', ') : s.documents_modified }}
                  </template>
                  <template v-if="s.findings">
                    <strong>Findings</strong>
                    {{ s.findings }}
                  </template>
                  <template v-if="s.whats_next">
                    <strong>What's Next</strong>
                    {{ s.whats_next }}
                  </template>
                </div>
              </td>
            </tr>
          </template>
          <tr v-if="!store.loading && store.items.length === 0">
            <td colspan="7" style="text-align:center; padding:40px; color:var(--text-muted);">No sessions found.</td>
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
import { useSessionsStore } from '../stores/index.js'
import Pagination from '../components/Pagination.vue'

const store = useSessionsStore()
const expandedId = ref(null)

function toggleExpand(id) {
  expandedId.value = expandedId.value === id ? null : id
}

function applyFilters() {
  store.page = 1
  store.fetchSessions()
}

function changePage(p) {
  store.setPage(p)
  store.fetchSessions()
}

function formatDateTime(dateStr) {
  if (!dateStr) return '—'
  const d = new Date(dateStr)
  return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) + ', ' +
    d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: false })
}

function formatDuration(minutes) {
  if (!minutes && minutes !== 0) return '—'
  return `${minutes} min`
}

function truncate(str, len) {
  if (!str) return '—'
  return str.length > len ? str.substring(0, len) + '…' : str
}

onMounted(() => store.fetchSessions())
</script>
