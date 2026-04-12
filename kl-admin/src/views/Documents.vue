<template>
  <div class="kl-view">
    <!-- Page Header -->
    <div class="kl-page-header">
      <h1 class="kl-page-title">Documents</h1>
      <span style="flex:1"></span>
      <button class="kl-btn kl-btn-primary" @click="showCreate = true">+ Create</button>
    </div>

    <!-- Bulk Action Bar -->
    <div class="kl-bulk-bar" :class="{ visible: selectedIds.length > 0 }">
      <span class="kl-bulk-count">{{ selectedIds.length }} selected</span>
      <button class="kl-btn kl-btn-sm kl-btn-secondary" @click="bulkAddTags">Add Tags</button>
      <button class="kl-btn kl-btn-sm kl-btn-secondary" @click="bulkChangeStatus">Change Status</button>
      <button class="kl-btn kl-btn-sm kl-btn-danger" @click="bulkArchive">Archive</button>
    </div>

    <!-- Toolbar -->
    <div class="kl-toolbar">
      <input
        type="search"
        class="kl-search"
        placeholder="Search documents…"
        v-model="searchInput"
        @input="onSearchInput"
      />
      <select class="kl-filter" v-model="store.filters.doc_type" @change="applyFilters">
        <option value="">All Types</option>
        <option v-for="t in docTypes" :key="t" :value="t">{{ t }}</option>
      </select>
      <select class="kl-filter" v-model="store.filters.status" @change="applyFilters">
        <option value="">All Statuses</option>
        <option value="active">active</option>
        <option value="draft">draft</option>
        <option value="seed">seed</option>
        <option value="archived">archived</option>
      </select>
      <select class="kl-filter" v-model="store.filters.tags" @change="applyFilters">
        <option value="">All Tags</option>
        <option v-for="tag in tagsStore.items" :key="tag.id" :value="tag.slug">{{ tag.name }}</option>
      </select>
    </div>

    <!-- Table -->
    <div class="kl-table-wrap" v-loading="store.loading">
      <table class="kl-table">
        <thead>
          <tr>
            <th class="col-check">
              <input type="checkbox" :checked="allSelected" @change="toggleAll" />
            </th>
            <th @click="sort('title')" style="cursor:pointer">Title</th>
            <th>Type</th>
            <th>Tags</th>
            <th @click="sort('status')" style="cursor:pointer">Status</th>
            <th>Source</th>
            <th @click="sort('updated_at')" style="cursor:pointer">Updated</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="doc in store.items"
            :key="doc.id"
            @click="goToDetail(doc.id)"
          >
            <td class="col-check" @click.stop>
              <input
                type="checkbox"
                :checked="selectedIds.includes(doc.id)"
                @change="toggleSelect(doc.id)"
              />
            </td>
            <td class="kl-cell-title">{{ doc.title }}</td>
            <td><TypeBadge :type="doc.doc_type" /></td>
            <td>
              <div class="kl-tags-row">
                <TagChip
                  v-for="tag in (doc.tags || [])"
                  :key="tag.id || tag.slug"
                  :label="tag.name || tag.slug"
                  :color="tag.color || ''"
                />
              </div>
            </td>
            <td><StatusBadge :value="doc.status" /></td>
            <td class="kl-cell-muted">{{ doc.source || 'plugin' }}</td>
            <td class="kl-cell-mono kl-cell-muted">{{ formatDate(doc.updated_at) }}</td>
          </tr>
          <tr v-if="!store.loading && store.items.length === 0">
            <td colspan="7" style="text-align:center; padding:40px; color:var(--text-muted);">
              No documents found.
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <Pagination
      :page="store.page"
      :per-page="store.perPage"
      :total="store.total"
      @update:page="changePage"
    />

    <!-- Bulk Tag Dialog -->
    <el-dialog v-model="showBulkTags" title="Add Tags" width="400px">
      <el-select
        v-model="bulkTagSelection"
        multiple
        filterable
        placeholder="Select tags"
        style="width:100%"
      >
        <el-option
          v-for="tag in tagsStore.items"
          :key="tag.id"
          :label="tag.name"
          :value="tag.slug"
        />
      </el-select>
      <template #footer>
        <el-button @click="showBulkTags = false">Cancel</el-button>
        <el-button type="primary" @click="confirmBulkTags">Apply</el-button>
      </template>
    </el-dialog>

    <!-- Bulk Status Dialog -->
    <el-dialog v-model="showBulkStatus" title="Change Status" width="360px">
      <el-select v-model="bulkStatusSelection" placeholder="Select status" style="width:100%">
        <el-option value="active" label="Active" />
        <el-option value="draft" label="Draft" />
        <el-option value="seed" label="Seed" />
        <el-option value="archived" label="Archived" />
      </el-select>
      <template #footer>
        <el-button @click="showBulkStatus = false">Cancel</el-button>
        <el-button type="primary" @click="confirmBulkStatus">Apply</el-button>
      </template>
    </el-dialog>

    <!-- Create Document Dialog -->
    <el-dialog v-model="showCreate" title="Create Document" width="560px">
      <div class="kl-create-form">
        <div class="kl-form-row">
          <label class="kl-form-label">Title</label>
          <el-input v-model="newDoc.title" placeholder="Document title" />
        </div>
        <div class="kl-form-row">
          <label class="kl-form-label">Type</label>
          <el-select v-model="newDoc.doc_type" placeholder="Select type" style="width:100%">
            <el-option v-for="t in docTypes" :key="t" :value="t" :label="t" />
          </el-select>
        </div>
        <div class="kl-form-row">
          <label class="kl-form-label">Slug</label>
          <el-input v-model="newDoc.slug" placeholder="Auto-generated from title if empty" />
        </div>
        <div class="kl-form-row">
          <label class="kl-form-label">Status</label>
          <el-select v-model="newDoc.status" style="width:100%">
            <el-option value="active" label="Active" />
            <el-option value="draft" label="Draft" />
            <el-option value="seed" label="Seed" />
          </el-select>
        </div>
        <div class="kl-form-row">
          <label class="kl-form-label">Source</label>
          <el-select v-model="newDoc.source" style="width:100%">
            <el-option value="human" label="Human" />
            <el-option value="ai" label="AI" />
            <el-option value="plugin" label="Plugin" />
          </el-select>
        </div>
        <div class="kl-form-row">
          <label class="kl-form-label">Tags</label>
          <el-select
            v-model="newDoc.tag_ids"
            multiple
            filterable
            placeholder="Select tags"
            style="width:100%"
          >
            <el-option
              v-for="tag in tagsStore.items"
              :key="tag.id"
              :label="tag.name"
              :value="tag.id"
            />
          </el-select>
        </div>
        <div class="kl-form-row">
          <label class="kl-form-label">Excerpt</label>
          <el-input v-model="newDoc.excerpt" type="textarea" :rows="2" placeholder="Short description" />
        </div>
        <div class="kl-form-row">
          <label class="kl-form-label">Content</label>
          <el-input v-model="newDoc.content" type="textarea" :rows="6" placeholder="Document content (markdown)" />
        </div>
      </div>
      <template #footer>
        <el-button @click="showCreate = false">Cancel</el-button>
        <el-button type="primary" :disabled="!newDoc.title || !newDoc.doc_type" @click="confirmCreate">Create</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useDocumentsStore, useTagsStore } from '../stores/index.js'
import TypeBadge from '../components/TypeBadge.vue'
import StatusBadge from '../components/StatusBadge.vue'
import TagChip from '../components/TagChip.vue'
import Pagination from '../components/Pagination.vue'

const router = useRouter()
const store = useDocumentsStore()
const tagsStore = useTagsStore()

const searchInput = ref(store.filters.search)
const selectedIds = ref([])
const showCreate = ref(false)

const defaultDoc = () => ({
  title: '',
  doc_type: '',
  slug: '',
  status: 'draft',
  source: 'human',
  content: '',
  excerpt: '',
  tag_ids: [],
})
const newDoc = reactive(defaultDoc())

// Bulk action dialogs
const showBulkTags = ref(false)
const showBulkStatus = ref(false)
const bulkTagSelection = ref([])
const bulkStatusSelection = ref('')

const docTypes = [
  'skill', 'agent', 'knowledge', 'course', 'config',
  'diagnostic', 'boot', 'essence', 'template',
  'site-identity', 'site-state', 'capabilities',
]

const allSelected = computed(() =>
  store.items.length > 0 && store.items.every(d => selectedIds.value.includes(d.id))
)

let searchTimer = null
function onSearchInput() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    store.setFilter('search', searchInput.value)
    store.fetchDocuments()
  }, 400)
}

function applyFilters() {
  store.page = 1
  store.fetchDocuments()
}

function sort(field) {
  store.setSort(field)
  store.fetchDocuments()
}

function changePage(p) {
  store.setPage(p)
  store.fetchDocuments()
}

function goToDetail(id) {
  router.push(`/documents/${id}`)
}

function toggleSelect(id) {
  const idx = selectedIds.value.indexOf(id)
  if (idx > -1) {
    selectedIds.value.splice(idx, 1)
  } else {
    selectedIds.value.push(id)
  }
}

function toggleAll() {
  if (allSelected.value) {
    selectedIds.value = []
  } else {
    selectedIds.value = store.items.map(d => d.id)
  }
}

function bulkAddTags() {
  bulkTagSelection.value = []
  showBulkTags.value = true
}

async function confirmBulkTags() {
  await store.bulkAction('add_tags', selectedIds.value, { tags: bulkTagSelection.value })
  showBulkTags.value = false
  selectedIds.value = []
  store.fetchDocuments()
}

function bulkChangeStatus() {
  bulkStatusSelection.value = ''
  showBulkStatus.value = true
}

async function confirmBulkStatus() {
  await store.bulkAction('change_status', selectedIds.value, { status: bulkStatusSelection.value })
  showBulkStatus.value = false
  selectedIds.value = []
  store.fetchDocuments()
}

async function bulkArchive() {
  await store.bulkAction('archive', selectedIds.value)
  selectedIds.value = []
  store.fetchDocuments()
}

async function confirmCreate() {
  const payload = { ...newDoc }
  if (!payload.slug) delete payload.slug
  const created = await store.createDocument(payload)
  showCreate.value = false
  Object.assign(newDoc, defaultDoc())
  if (created?.id) {
    router.push(`/documents/${created.id}`)
  } else {
    store.fetchDocuments()
  }
}

function formatDate(dateStr) {
  if (!dateStr) return '—'
  const d = new Date(dateStr)
  const now = new Date()
  const diffMs = now - d
  const diffDays = Math.floor(diffMs / 86400000)
  if (diffDays === 0) return 'Today'
  if (diffDays === 1) return '1d ago'
  if (diffDays < 7) return `${diffDays}d ago`
  return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
}

onMounted(() => {
  store.fetchDocuments()
  tagsStore.fetchTags()
})
</script>
