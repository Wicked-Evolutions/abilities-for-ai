import { defineStore } from 'pinia'
import { api } from '../api.js'

export const useObservationsStore = defineStore('observations', {
  state: () => ({
    items: [],
    total: 0,
    page: 1,
    perPage: 20,
    loading: false,
    filters: {
      status: 'open',
      category: '',
      severity: '',
    },
  }),

  actions: {
    async fetchObservations() {
      this.loading = true
      try {
        const params = { page: this.page, per_page: this.perPage }
        if (this.filters.status) params.status = this.filters.status
        if (this.filters.category) params.category = this.filters.category
        if (this.filters.severity) params.severity = this.filters.severity

        const data = await api.get('observations', params)
        this.items = Array.isArray(data) ? data : (data.items || [])
        this.total = Array.isArray(data) ? data.length : (data.total || 0)
      } finally {
        this.loading = false
      }
    },

    async resolveObservation(id, note = '') {
      await api.put(`observations/${id}/resolve`, { note })
      await this.fetchObservations()
    },

    async bulkAction(action, ids) {
      await api.post('observations/bulk-action', { action, ids })
      await this.fetchObservations()
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
