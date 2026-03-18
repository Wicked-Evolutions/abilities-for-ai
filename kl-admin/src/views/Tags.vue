<template>
  <div class="kl-view">
    <div class="kl-page-header">
      <h1 class="kl-page-title">Tags</h1>
      <span style="flex:1"></span>
    </div>

    <!-- Create Form -->
    <div class="kl-create-row">
      <el-color-picker
        v-model="newColor"
        size="small"
        :predefine="presetColors"
      />
      <input type="text" class="kl-input" v-model="newName" placeholder="Tag title…" style="width:200px;" @keyup.enter="createTag" />
      <input type="text" class="kl-input" v-model="newDescription" placeholder="Description (optional)…" style="flex:1;" @keyup.enter="createTag" />
      <button class="kl-btn kl-btn-primary" @click="createTag" :disabled="!newName.trim()">+ Create Tag</button>
    </div>

    <div class="kl-table-wrap" v-loading="store.loading">
      <table class="kl-table">
        <thead>
          <tr>
            <th style="width:40px;">Color</th>
            <th>Title</th>
            <th>Slug</th>
            <th>Description</th>
            <th>Usage</th>
            <th>Created</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="tag in store.items" :key="tag.id">
            <td><span class="kl-color-dot" :style="{ background: tag.color || '#9E9E9E' }"></span></td>
            <td class="kl-cell-title">{{ tag.name }}</td>
            <td class="kl-cell-mono kl-cell-muted">{{ tag.slug }}</td>
            <td class="kl-cell-muted">{{ tag.description || '' }}</td>
            <td class="kl-cell-mono">{{ tag.usage_count || 0 }}</td>
            <td class="kl-cell-mono kl-cell-muted">{{ formatDate(tag.created_at) }}</td>
            <td>
              <div style="display:flex; gap:6px;">
                <button class="kl-btn kl-btn-sm kl-btn-ghost" @click="openEdit(tag)">Edit</button>
                <button class="kl-btn kl-btn-sm kl-btn-danger" @click="openDelete(tag)">Delete</button>
              </div>
            </td>
          </tr>
          <tr v-if="!store.loading && store.items.length === 0">
            <td colspan="7" style="text-align:center; padding:40px; color:var(--text-muted);">No tags yet. Create one above.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="kl-pagination">
      <span class="kl-pagination-info">{{ store.items.length }} tags</span>
      <div class="kl-pagination-pages"></div>
    </div>

    <!-- Edit Dialog -->
    <el-dialog v-model="showEdit" title="Edit Tag" width="420px">
      <div style="display:flex; flex-direction:column; gap:14px;">
        <div style="display:flex; align-items:center; gap:12px;">
          <el-color-picker v-model="editColor" size="small" :predefine="presetColors" />
          <input type="text" class="kl-input" v-model="editName" placeholder="Tag title" style="flex:1;" />
        </div>
        <input type="text" class="kl-input" v-model="editDescription" placeholder="Description (optional)" style="width:100%;" />
      </div>
      <template #footer>
        <el-button @click="showEdit = false">Cancel</el-button>
        <el-button type="primary" @click="confirmEdit">Save</el-button>
      </template>
    </el-dialog>

    <!-- Delete Confirmation -->
    <el-dialog v-model="showDelete" title="Delete Tag" width="400px">
      <p>Are you sure you want to delete <strong>{{ deleteTarget?.name }}</strong>?</p>
      <p v-if="deleteTarget?.usage_count" style="margin-top:8px; color:var(--sev-attention);">
        This tag is used on {{ deleteTarget.usage_count }} items. Deleting it will remove all assignments.
      </p>
      <template #footer>
        <el-button @click="showDelete = false">Cancel</el-button>
        <el-button type="danger" @click="confirmDelete">Delete</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useTagsStore } from '../stores/tags.js'

const store = useTagsStore()

const presetColors = [
  '#66BB6A', '#42A5F5', '#FFB74D', '#7E57C2', '#AB47BC',
  '#5C6BC0', '#EC407A', '#29B6F6', '#EF5350', '#8D6E63',
  '#26A69A', '#FF8A65', '#FFCA28', '#78909C', '#9E9E9E',
]

// Create form
const newName = ref('')
const newColor = ref('#66BB6A')
const newDescription = ref('')

// Edit dialog
const showEdit = ref(false)
const editTarget = ref(null)
const editName = ref('')
const editColor = ref('')
const editDescription = ref('')

// Delete dialog
const showDelete = ref(false)
const deleteTarget = ref(null)

async function createTag() {
  if (!newName.value.trim()) return
  await store.createTag({
    name: newName.value.trim(),
    color: newColor.value,
    description: newDescription.value.trim() || undefined,
  })
  newName.value = ''
  newDescription.value = ''
  newColor.value = '#66BB6A'
}

function openEdit(tag) {
  editTarget.value = tag
  editName.value = tag.name
  editColor.value = tag.color || '#9E9E9E'
  editDescription.value = tag.description || ''
  showEdit.value = true
}

async function confirmEdit() {
  await store.updateTag(editTarget.value.id, {
    name: editName.value.trim(),
    color: editColor.value,
    description: editDescription.value.trim() || undefined,
  })
  showEdit.value = false
}

function openDelete(tag) {
  deleteTarget.value = tag
  showDelete.value = true
}

async function confirmDelete() {
  await store.deleteTag(deleteTarget.value.id)
  showDelete.value = false
}

function formatDate(dateStr) {
  if (!dateStr) return '—'
  const d = new Date(dateStr)
  return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
}

onMounted(() => store.fetchTags(true))
</script>
