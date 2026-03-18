import { defineStore } from 'pinia'
import { api } from '../api.js'

export const useSessionsStore = defineStore('sessions', {
  state: () => ({
    items: [],
    total: 0,
    page: 1,
    perPage: 20,
    loading: false,
    filters: {
      agent_type: '',
      date_range: '',
    },
  }),

  actions: {
    async fetchSessions() {
      this.loading = true
      try {
        const params = { page: this.page, per_page: this.perPage }
        if (this.filters.agent_type) params.agent_type = this.filters.agent_type
        if (this.filters.date_range) params.date_range = this.filters.date_range

        const data = await api.get('sessions', params)
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
