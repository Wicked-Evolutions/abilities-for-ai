<template>
  <div class="kl-view">
    <div class="kl-page-header">
      <h1 class="kl-page-title">Dashboard</h1>
      <span style="flex:1"></span>
      <span style="font-family:var(--font-mono); font-size:.75rem; color:var(--text-muted);">{{ siteName }}</span>
    </div>

    <!-- Stat Cards -->
    <div class="kl-stats-grid" v-loading="loading">
      <div class="kl-stat-card">
        <div class="kl-stat-label">Total Documents</div>
        <div class="kl-stat-value">{{ stats.documents?.total || 0 }}</div>
        <div class="kl-stat-sub">
          {{ stats.documents?.active || 0 }} active ·
          {{ stats.documents?.draft || 0 }} draft ·
          {{ stats.documents?.seed || 0 }} seed ·
          {{ stats.documents?.archived || 0 }} archived
        </div>
      </div>
      <div class="kl-stat-card">
        <div class="kl-stat-label">Sessions</div>
        <div class="kl-stat-value">{{ stats.sessions?.total || 0 }}</div>
        <div class="kl-stat-sub">
          {{ stats.sessions?.last_7d || 0 }} last 7d ·
          {{ stats.sessions?.last_30d || 0 }} last 30d
        </div>
      </div>
      <div class="kl-stat-card">
        <div class="kl-stat-label">Open Observations</div>
        <div class="kl-stat-value" style="color:var(--sev-attention);">{{ stats.observations?.open || 0 }}</div>
        <div class="kl-stat-sub">
          {{ stats.observations?.action || 0 }} action ·
          {{ stats.observations?.attention || 0 }} attention ·
          {{ stats.observations?.info || 0 }} info
        </div>
      </div>
      <div class="kl-stat-card">
        <div class="kl-stat-label">Tags</div>
        <div class="kl-stat-value">{{ stats.tags?.total || 0 }}</div>
        <div class="kl-stat-sub">{{ stats.tags?.assignments || 0 }} total assignments</div>
      </div>
      <div class="kl-stat-card">
        <div class="kl-stat-label">Activity</div>
        <div class="kl-stat-value">{{ stats.activity?.total || 0 }}</div>
        <div class="kl-stat-sub">
          {{ stats.activity?.total_error || 0 }} errors
        </div>
      </div>
    </div>

    <!-- Two columns -->
    <div class="kl-dashboard-grid">
      <!-- Recent Sessions -->
      <div>
        <div class="kl-section-title">Recent Sessions</div>
        <div v-if="recentSessions.length" class="kl-feed">
          <div v-for="s in recentSessions" :key="s.id" class="kl-feed-item">
            <div class="kl-feed-body">
              <div style="display:flex; align-items:center; gap:8px;">
                <span class="kl-badge badge-agent-type">{{ s.agent_type || 'unknown' }}</span>
                <span class="kl-feed-title" style="margin:0;">#{{ (s.session_id || s.id || '').toString().substring(0, 6) }}</span>
              </div>
              <div class="kl-feed-sub">{{ formatDate(s.started_at || s.created_at) }} · {{ formatDuration(s.duration_minutes) }} · {{ s.model || '' }}</div>
              <div v-if="s.summary" style="margin-top:4px; font-size:.8125rem; color:var(--text-secondary);">{{ s.summary }}</div>
            </div>
          </div>
        </div>
        <p v-else style="color:var(--text-muted); font-size:.875rem;">No sessions yet.</p>
      </div>

      <!-- Observations + Recent Docs -->
      <div>
        <div class="kl-section-title">Open Observations</div>
        <div v-if="openObservations.length" class="kl-feed">
          <div v-for="obs in openObservations" :key="obs.id" class="kl-feed-item">
            <div class="kl-feed-body">
              <div style="display:flex; align-items:center; gap:6px; margin-bottom:4px;">
                <span class="kl-badge" :class="`badge-${obs.severity || 'info'}`">{{ obs.severity || 'info' }}</span>
                <span class="kl-badge badge-agent-type">{{ obs.category || 'general' }}</span>
              </div>
              <div class="kl-feed-title">{{ obs.title || obs.content }}</div>
              <div class="kl-feed-sub">#{{ (obs.session_id || '').toString().substring(0, 6) }} · {{ formatDate(obs.created_at) }}</div>
            </div>
          </div>
        </div>
        <p v-else style="color:var(--text-muted); font-size:.875rem;">No open observations.</p>

        <div class="kl-section-title" style="margin-top:24px;">Recently Modified Documents</div>
        <div v-if="recentDocuments.length" class="kl-feed">
          <div v-for="doc in recentDocuments" :key="doc.id" class="kl-feed-item" @click="$router.push(`/documents/${doc.id}`)" style="cursor:pointer;">
            <div class="kl-feed-body">
              <div style="display:flex; align-items:center; gap:8px;">
                <span class="kl-badge" :class="`badge-${doc.doc_type}`">{{ doc.doc_type }}</span>
                <span class="kl-feed-title" style="margin:0;">{{ doc.title }}</span>
              </div>
              <div class="kl-feed-sub">{{ doc.version ? 'v' + doc.version + ' · ' : '' }}Updated {{ formatDate(doc.updated_at) }}</div>
            </div>
          </div>
        </div>
        <p v-else style="color:var(--text-muted); font-size:.875rem;">No documents yet.</p>
      </div>
    </div>

    <!-- Recent Activity -->
    <div style="margin-top:24px;">
      <div class="kl-section-title">Recent Activity</div>
      <div v-if="recentActivity.length" class="kl-feed">
        <div v-for="a in recentActivity" :key="a.id" class="kl-feed-item" @click="$router.push('/activity')" style="cursor:pointer;">
          <div class="kl-feed-body">
            <div style="display:flex; align-items:center; gap:8px;">
              <span class="kl-badge" :class="a.status === 'error' ? 'badge-action' : 'badge-agent-type'">{{ a.status }}</span>
              <span class="kl-feed-title" style="margin:0; font-family:var(--font-mono); font-size:.8125rem;">{{ a.ability_name }}</span>
              <span style="font-family:var(--font-mono); font-size:.75rem; color:var(--text-muted);">{{ a.duration_ms }}ms</span>
            </div>
            <div class="kl-feed-sub">{{ formatDate(a.created_at) }}{{ a.error_code ? ' · ' + a.error_code : '' }}</div>
          </div>
        </div>
      </div>
      <p v-else style="color:var(--text-muted); font-size:.875rem;">No activity recorded yet.</p>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { api } from '../api.js'

const loading = ref(true)
const stats = ref({})
const recentSessions = ref([])
const openObservations = ref([])
const recentDocuments = ref([])
const recentActivity = ref([])
const siteName = window.abilitiesKL?.site_name || ''

async function loadDashboard() {
  loading.value = true
  try {
    const data = await api.get('dashboard/stats')
    stats.value = data.stats || data || {}
    recentSessions.value = data.recent_sessions || []
    openObservations.value = data.open_observations || []
    recentDocuments.value = data.recent_documents || []
    recentActivity.value = data.activity?.recent || []
  } catch (e) {
    // Dashboard stats endpoint may not exist yet
  } finally {
    loading.value = false
  }
}

function formatDate(dateStr) {
  if (!dateStr) return '—'
  const d = new Date(dateStr)
  return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
}

function formatDuration(minutes) {
  if (!minutes) return ''
  return `${minutes} min`
}

onMounted(loadDashboard)
</script>
