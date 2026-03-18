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
    filters: {
      doc_type: '',
      status: 'active',
      search: '',
    },
  }),

  actions: {
    async fetchDocuments() {
      this.loading = true
      try {
        const params = { page: this.page, per_page: this.perPage }
        if (this.filters.doc_type) params.doc_type = this.filters.doc_type
        if (this.filters.status) params.status = this.filters.status
        if (this.filters.search) params.search = this.filters.search

        const data = await api.get('documents', params)
        this.items = data.items || []
        this.total = data.total || 0
      } finally {
        this.loading = false
      }
    },

    async fetchDocument(id) {
      this.loading = true
      try {
        this.current = await api.get(`documents/${id}`)
      } finally {
        this.loading = false
      }
    },
  },
})
