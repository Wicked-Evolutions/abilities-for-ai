import { defineStore } from 'pinia'
import { api } from '../api.js'

export const useBoundaryStore = defineStore('boundary', {
  state: () => ({
    items: [],
    total: 0,
    page: 1,
    perPage: 20,
    loading: false,
    filters: {
      event: '',
      severity: '',
      session_id: '',
      date_range: '',
    },
  }),

  actions: {
    async fetchBoundary() {
      this.loading = true
      try {
        const params = {
          page: this.page,
          per_page: this.perPage,
        }
        if (this.filters.event) params.event = this.filters.event
        if (this.filters.severity) params.severity = this.filters.severity
        if (this.filters.session_id) params.session_id = this.filters.session_id
        if (this.filters.date_range) {
          const days = parseInt(this.filters.date_range)
          if (days) {
            const from = new Date(Date.now() - days * 86400000)
            params.date_from = from.toISOString().slice(0, 19).replace('T', ' ')
          }
        }
        const data = await api.get('boundary', params)
        this.items = Array.isArray(data) ? data : (data.items || [])
        this.total = Array.isArray(data) ? data.length : (data.total || 0)
      } finally {
        this.loading = false
      }
    },

    setFilter(key, value) {
      this.filters[key] = value
      this.page = 1
    },

    setPage(page) {
      this.page = page
    },
  },
})
