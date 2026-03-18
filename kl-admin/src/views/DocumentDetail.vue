<template>
  <div class="kl-view" v-loading="store.currentLoading">
    <template v-if="doc">
      <!-- Detail Header -->
      <div class="kl-detail-header">
        <a class="kl-back" @click="$router.push('/documents')">← Documents</a>
        <div class="kl-detail-title-row">
          <TypeBadge :type="doc.doc_type" />
          <h1 class="kl-detail-title">{{ doc.title }}</h1>
          <select
            class="kl-filter"
            style="margin-left:auto; width:120px;"
            :value="doc.status"
            @change="changeStatus($event.target.value)"
          >
            <option value="active">Active</option>
            <option value="draft">Draft</option>
            <option value="seed">Seed</option>
            <option value="archived">Archived</option>
          </select>
        </div>
        <div class="kl-detail-meta">
          <span>v{{ doc.version || '0.1.0' }}</span>
          <span>Created {{ formatFullDate(doc.created_at) }}</span>
          <span>Updated {{ formatFullDate(doc.updated_at) }}</span>
          <span>Source: {{ doc.source || 'plugin' }}</span>
          <span v-if="doc.locked">🔒 Locked</span>
        </div>
        <div class="kl-tags-row">
          <TagChip
            v-for="tag in (doc.tags || [])"
            :key="tag.id || tag.slug"
            :label="tag.name || tag.slug"
            :color="tag.color || ''"
            :removable="!doc.locked"
            @remove="removeTag(tag)"
          />
          <span v-if="!doc.locked" class="kl-tag-add" title="Add tag" @click="showTagPicker = true">+</span>
        </div>
        <div style="margin-top:14px; display:flex; gap:10px;">
          <button class="kl-btn kl-btn-secondary kl-btn-sm" @click="showPublish = true">📤 Publish to Blog</button>
          <button class="kl-btn kl-btn-secondary kl-btn-sm">📋 Export</button>
        </div>
      </div>

      <!-- Lock Banner -->
      <div v-if="doc.locked" style="padding: 0 28px; padding-top: 20px;">
        <div class="kl-lock-banner">
          <span class="lock-icon">🔒</span>
          <div>
            <strong style="display:block; font-size:.875rem; margin-bottom:2px;">This document is plugin-managed.</strong>
            Fork to create an editable copy. The original stays pristine for plugin updates.
          </div>
          <button class="kl-btn kl-btn-sm kl-btn-primary" style="margin-left:auto;" @click="forkDoc">Fork</button>
        </div>
      </div>

      <!-- Tabs -->
      <div class="kl-tabs">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          class="kl-tab"
          :class="{ active: activeTab === tab.key }"
          @click="activeTab = tab.key"
        >
          {{ tab.label }}
        </button>
      </div>

      <!-- Tab: Content -->
      <div v-if="activeTab === 'content'" class="kl-view-body">
        <div style="display:flex; justify-content:flex-end; margin-bottom:14px; gap:6px;">
          <button
            class="kl-btn kl-btn-sm kl-btn-ghost"
            :style="{ opacity: !editing ? 1 : .5 }"
            @click="editing = false"
          >Preview</button>
          <button
            v-if="!doc.locked"
            class="kl-btn kl-btn-sm kl-btn-ghost"
            :style="{ opacity: editing ? 1 : .5 }"
            @click="editing = true"
          >Edit Source</button>
        </div>
        <div v-if="!editing" class="kl-content-preview" v-html="renderedContent"></div>
        <div v-else>
          <textarea
            v-model="editContent"
            style="width:100%; min-height:400px; background:var(--bg-raised); border:1px solid var(--border-default); border-radius:var(--radius-md); padding:16px; color:var(--text-primary); font-family:var(--font-mono); font-size:.875rem; line-height:1.6; resize:vertical; outline:none;"
            @focus="$event.target.style.borderColor = 'var(--accent)'"
            @blur="$event.target.style.borderColor = 'var(--border-default)'"
          ></textarea>
          <div style="margin-top:10px; display:flex; gap:8px; justify-content:flex-end;">
            <button class="kl-btn kl-btn-secondary kl-btn-sm" @click="editing = false; editContent = doc.content">Cancel</button>
            <button class="kl-btn kl-btn-primary kl-btn-sm" @click="saveContent">Save</button>
          </div>
        </div>
      </div>

      <!-- Tab: Metadata -->
      <div v-if="activeTab === 'metadata'" class="kl-view-body">
        <div class="kl-meta-grid">
          <template v-for="(val, key) in metaFields" :key="key">
            <div class="kl-meta-key">{{ key }}</div>
            <div class="kl-meta-val">{{ formatMetaValue(val) }}</div>
          </template>
        </div>
      </div>

      <!-- Tab: Revisions -->
      <div v-if="activeTab === 'revisions'" class="kl-view-body" v-loading="store.revisionsLoading">
        <div
          v-for="(rev, i) in store.revisions"
          :key="rev.version || i"
          class="kl-revision-item"
        >
          <span class="kl-revision-ver">v{{ rev.version || '?' }}</span>
          <div class="kl-revision-info">
            <div class="kl-revision-summary">{{ rev.summary || rev.change_summary || 'No summary' }}</div>
            <div class="kl-revision-meta">{{ formatFullDate(rev.created_at) }} · {{ rev.author || 'System' }}</div>
          </div>
          <button
            v-if="i === 0"
            class="kl-btn kl-btn-sm kl-btn-ghost"
          >Current</button>
          <button
            v-else
            class="kl-btn kl-btn-sm kl-btn-secondary"
            @click="restoreRevision(rev)"
          >Restore</button>
        </div>
        <p v-if="!store.revisionsLoading && store.revisions.length === 0" style="color:var(--text-muted);">
          No revisions yet.
        </p>
      </div>

      <!-- Tab: Sessions -->
      <div v-if="activeTab === 'sessions'" class="kl-view-body">
        <div v-if="sessions.length" class="kl-feed">
          <div v-for="s in sessions" :key="s.id || s.session_id" class="kl-feed-item">
            <div class="kl-feed-body">
              <div class="kl-feed-title">Session #{{ (s.session_id || s.id || '').substring(0, 6) }} — {{ s.agent_type || 'Unknown' }}</div>
              <div class="kl-feed-sub">{{ formatFullDate(s.created_at || s.started_at) }} · {{ s.model || '' }}</div>
              <div v-if="s.summary" style="margin-top:6px; font-size:.8125rem; color:var(--text-secondary);">
                {{ s.summary }}
              </div>
            </div>
            <div class="kl-feed-right">
              <span v-if="s.agent_type" class="kl-badge badge-agent-type">{{ s.agent_type }}</span>
            </div>
          </div>
        </div>
        <p v-else style="color:var(--text-muted);">No sessions reference this document.</p>
      </div>

      <!-- Tab: Observations -->
      <div v-if="activeTab === 'observations'" class="kl-view-body">
        <div v-if="observations.length" class="kl-feed">
          <div v-for="obs in observations" :key="obs.id" class="kl-feed-item">
            <div class="kl-feed-body">
              <div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">
                <span class="kl-badge" :class="`badge-${obs.severity || 'info'}`">{{ obs.severity || 'info' }}</span>
                <span class="kl-badge" :class="`badge-${obs.status || 'open'}`">{{ obs.status || 'open' }}</span>
              </div>
              <div class="kl-feed-title">{{ obs.title || obs.content }}</div>
              <div class="kl-feed-sub">Session #{{ (obs.session_id || '').substring(0, 6) }} · {{ formatFullDate(obs.created_at) }}</div>
              <div v-if="obs.content && obs.title" style="margin-top:6px; font-size:.8125rem; color:var(--text-secondary);">
                {{ obs.content }}
              </div>
            </div>
            <div class="kl-feed-right">
              <button
                v-if="obs.status === 'open'"
                class="kl-btn kl-btn-sm kl-btn-success"
                @click="resolveObservation(obs)"
              >Resolve</button>
              <span v-else style="font-family:var(--font-mono); font-size:.75rem; color:var(--status-active);">✓</span>
            </div>
          </div>
        </div>
        <p v-else style="color:var(--text-muted);">No observations linked to this document.</p>
      </div>

      <!-- Tag Picker Dialog -->
      <el-dialog v-model="showTagPicker" title="Add Tag" width="360px">
        <el-select
          v-model="tagToAdd"
          filterable
          placeholder="Search tags..."
          style="width:100%"
        >
          <el-option
            v-for="tag in availableTags"
            :key="tag.id"
            :label="tag.name"
            :value="tag.slug"
          />
        </el-select>
        <template #footer>
          <el-button @click="showTagPicker = false">Cancel</el-button>
          <el-button type="primary" @click="addTag">Add</el-button>
        </template>
      </el-dialog>

      <!-- Publish Sidebar -->
      <transition name="slide">
        <div v-if="showPublish" class="kl-publish-sidebar" style="display:flex">
          <div class="kl-publish-header">
            <h3>📤 Publish to Blog</h3>
            <button class="kl-btn kl-btn-sm kl-btn-ghost" @click="showPublish = false">✕</button>
          </div>
          <div class="kl-publish-body">
            <div class="kl-field-group">
              <label class="kl-field-label">Title</label>
              <input type="text" class="kl-field-input" :value="doc.title" />
            </div>
            <div class="kl-field-group">
              <label class="kl-field-label">Content</label>
              <div style="background:var(--bg-raised); border:1px solid var(--border-default); border-radius:6px; padding:12px; font-size:.8125rem; color:var(--text-muted);">
                <code style="font-size:.75rem;">&#123;&#123;document.content||md_to_blocks&#125;&#125;</code>
                <div style="margin-top:6px; font-size:.75rem;">Markdown → Gutenberg blocks at publish time</div>
              </div>
            </div>
            <div class="kl-field-group">
              <label class="kl-field-label">Excerpt</label>
              <textarea class="kl-field-textarea" rows="3" :value="doc.excerpt || ''"></textarea>
            </div>
            <div class="kl-field-group">
              <label class="kl-field-label">Post Status</label>
              <select class="kl-filter" style="width:100%">
                <option>Draft</option>
                <option>Publish</option>
                <option>Private</option>
              </select>
            </div>
          </div>
          <div class="kl-publish-footer">
            <button class="kl-btn kl-btn-secondary" @click="showPublish = false">Cancel</button>
            <button class="kl-btn kl-btn-primary">Publish</button>
          </div>
        </div>
      </transition>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useDocumentsStore } from '../stores/documents.js'
import { useTagsStore } from '../stores/tags.js'
import TypeBadge from '../components/TypeBadge.vue'
import TagChip from '../components/TagChip.vue'

const route = useRoute()
const router = useRouter()
const store = useDocumentsStore()
const tagsStore = useTagsStore()

const activeTab = ref('content')
const editing = ref(false)
const editContent = ref('')
const showTagPicker = ref(false)
const tagToAdd = ref('')
const showPublish = ref(false)

const tabs = [
  { key: 'content', label: 'Content' },
  { key: 'metadata', label: 'Metadata' },
  { key: 'revisions', label: 'Revisions' },
  { key: 'sessions', label: 'Sessions' },
  { key: 'observations', label: 'Observations' },
]

const doc = computed(() => store.current)

const renderedContent = computed(() => {
  if (!doc.value?.content) return '<p style="color:var(--text-muted);">No content.</p>'
  // Basic markdown-to-HTML: headings, paragraphs, code, lists
  return simpleMarkdown(doc.value.content)
})

const metaFields = computed(() => {
  if (!doc.value) return {}
  const d = doc.value
  return {
    doc_type: d.doc_type,
    slug: d.slug,
    locked: d.locked ? 'true' : 'false',
    source: d.source || 'plugin',
    version: d.version || '0.1.0',
    parent_id: d.parent_id || 'null',
    author_id: d.author_id || '0 (system)',
    ...(d.metadata && typeof d.metadata === 'object' ? d.metadata : {}),
  }
})

const sessions = computed(() => doc.value?.sessions || [])
const observations = computed(() => doc.value?.observations || [])

const availableTags = computed(() => {
  const existingSlugs = (doc.value?.tags || []).map(t => t.slug)
  return tagsStore.items.filter(t => !existingSlugs.includes(t.slug))
})

async function loadDocument() {
  const id = route.params.id
  await store.fetchDocument(id)
  if (doc.value) {
    editContent.value = doc.value.content || ''
  }
  store.fetchRevisions(id)
  tagsStore.fetchTags()
}

function changeStatus(status) {
  store.updateDocument(route.params.id, { status })
}

async function saveContent() {
  await store.updateDocument(route.params.id, { content: editContent.value })
  editing.value = false
}

async function forkDoc() {
  const forked = await store.forkDocument(route.params.id)
  if (forked?.id) {
    router.push(`/documents/${forked.id}`)
  }
}

async function removeTag(tag) {
  const currentTags = (doc.value.tags || []).filter(t => t.slug !== tag.slug).map(t => t.slug)
  await store.updateDocument(route.params.id, { tags: currentTags })
}

async function addTag() {
  if (!tagToAdd.value) return
  const currentTags = (doc.value.tags || []).map(t => t.slug)
  currentTags.push(tagToAdd.value)
  await store.updateDocument(route.params.id, { tags: currentTags })
  showTagPicker.value = false
  tagToAdd.value = ''
}

async function restoreRevision(rev) {
  await store.updateDocument(route.params.id, { restore_version: rev.version })
  loadDocument()
}

function resolveObservation(obs) {
  // Placeholder — observations resolve endpoint TBD
}

function formatFullDate(dateStr) {
  if (!dateStr) return '—'
  const d = new Date(dateStr)
  return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

function formatMetaValue(val) {
  if (val === null || val === undefined) return 'null'
  if (typeof val === 'object') return JSON.stringify(val)
  return String(val)
}

function simpleMarkdown(md) {
  if (!md) return ''
  let html = md
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')

  // Headings
  html = html.replace(/^### (.+)$/gm, '<h3>$1</h3>')
  html = html.replace(/^## (.+)$/gm, '<h2>$1</h2>')
  html = html.replace(/^# (.+)$/gm, '<h1>$1</h1>')

  // Inline code
  html = html.replace(/`([^`]+)`/g, '<code>$1</code>')

  // Bold and italic
  html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
  html = html.replace(/\*(.+?)\*/g, '<em>$1</em>')

  // List items
  html = html.replace(/^- (.+)$/gm, '<li>$1</li>')

  // Wrap consecutive <li> in <ul>
  html = html.replace(/((?:<li>.*<\/li>\n?)+)/g, '<ul>$1</ul>')

  // Paragraphs: wrap non-tag lines
  html = html.replace(/^(?!<[hulo])(.*\S.*)$/gm, '<p>$1</p>')

  // Clean up empty paragraphs
  html = html.replace(/<p><\/p>/g, '')

  return html
}

watch(() => route.params.id, () => {
  if (route.params.id) loadDocument()
})

onMounted(loadDocument)
</script>

<style scoped>
.slide-enter-active,
.slide-leave-active {
  transition: transform .25s cubic-bezier(0.22, 1, 0.36, 1);
}
.slide-enter-from,
.slide-leave-to {
  transform: translateX(100%);
}
</style>
