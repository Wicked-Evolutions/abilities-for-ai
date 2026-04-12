import { defineStore } from 'pinia'
import { api } from '../api.js'

export const useActivityStore = defineStore('activity', {
  state: () => ({
    items: [],
    total: 0,
    page: 1,
    perPage: 20,
    loading: false,
    filters: {
      ability_name: '',
      category: '',
      user_id: '',
      status: '',
      date_range: '',
    },
  }),

  actions: {
    async fetchActivity() {
      this.loading = true
      try {
        const params = { page: this.page, per_page: this.perPage }
        if (this.filters.ability_name) params.ability_name = this.filters.ability_name
        if (this.filters.category) params.category = this.filters.category
        if (this.filters.user_id) params.user_id = this.filters.user_id
        if (this.filters.status) params.status = this.filters.status
        if (this.filters.date_range) {
          const now = new Date()
          const days = parseInt(this.filters.date_range)
          if (days) {
            const from = new Date(now.getTime() - days * 86400000)
            params.date_from = from.toISOString().slice(0, 19).replace('T', ' ')
          }
        }

        const data = await api.get('activity', params)
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
