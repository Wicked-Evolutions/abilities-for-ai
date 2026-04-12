import { defineStore } from 'pinia'
import { api } from '../api.js'

export const useDocumentsStore = defineStore('documents', {
  state: () => ({
    items: [],
    total: 0,
    page: 1,
    perPage: 20,
    loading: false,
    current: null,
    currentLoading: false,
    revisions: [],
    revisionsLoading: false,
    filters: {
      doc_type: '',
      status: '',
      search: '',
      tags: '',
    },
    orderBy: 'updated_at',
    order: 'desc',
  }),

  actions: {
    async fetchDocuments() {
      this.loading = true
      try {
        const params = {
          page: this.page,
          per_page: this.perPage,
          order_by: this.orderBy,
          order: this.order,
        }
        if (this.filters.doc_type) params.doc_type = this.filters.doc_type
        params.status = this.filters.status || 'all'
        if (this.filters.search) params.search = this.filters.search
        if (this.filters.tags) params.tags = this.filters.tags

        const data = await api.get('documents', params)
        this.items = Array.isArray(data) ? data : (data.items || [])
        this.total = Array.isArray(data) ? data.length : (data.total || 0)
      } finally {
        this.loading = false
      }
    },

    async fetchDocument(id) {
      this.currentLoading = true
      try {
        this.current = await api.get(`documents/${id}`)
      } finally {
        this.currentLoading = false
      }
    },

    async updateDocument(id, data) {
      const updated = await api.put(`documents/${id}`, data)
      this.current = updated
      return updated
    },

    async forkDocument(id) {
      const forked = await api.post(`documents/${id}/fork`)
      return forked
    },

    async fetchRevisions(id) {
      this.revisionsLoading = true
      try {
        const data = await api.get(`documents/${id}/revisions`)
        this.revisions = data.items || data || []
      } finally {
        this.revisionsLoading = false
      }
    },

    async createDocument(data) {
      const doc = await api.post('documents', data)
      return doc
    },

    async bulkAction(action, ids, extra = {}) {
      return api.post('documents/bulk-action', { action, ids, ...extra })
    },

    setFilter(key, value) {
      this.filters[key] = value
      this.page = 1
    },

    setPage(page) {
      this.page = page
    },

    setSort(field) {
      if (this.orderBy === field) {
        this.order = this.order === 'asc' ? 'desc' : 'asc'
      } else {
        this.orderBy = field
        this.order = 'desc'
      }
    },
  },
})
