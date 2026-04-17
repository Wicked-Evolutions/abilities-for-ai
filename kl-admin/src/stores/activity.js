import { defineStore } from 'pinia'
import { api } from '../api.js'

export const useActivityStore = defineStore('activity', {
  state: () => ({
    items: [],
    total: 0,
    page: 1,
    perPage: 20,
    loading: false,
    orderby: 'created_at',
    order: 'DESC',
    filters: {
      ability_name: '',
      category: '',
      user_id: '',
      status: '',
      date_range: '',
      // v0.6.0 (issue #123):
      caller_origin: '',
      is_compiled: '',
      replaced_surface: '',
      response_hash: '',
    },
  }),

  actions: {
    async fetchActivity() {
      this.loading = true
      try {
        const params = {
          page: this.page,
          per_page: this.perPage,
          orderby: this.orderby,
          order: this.order,
        }
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
        // v0.6.0 filters.
        if (this.filters.caller_origin) params.caller_origin = this.filters.caller_origin
        if (this.filters.is_compiled !== '' && this.filters.is_compiled !== null && this.filters.is_compiled !== undefined) {
          params.is_compiled = this.filters.is_compiled === true || this.filters.is_compiled === 'true' || this.filters.is_compiled === '1'
        }
        if (this.filters.replaced_surface) params.replaced_surface = this.filters.replaced_surface
        if (this.filters.response_hash) params.response_hash = this.filters.response_hash

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

    setSort(orderby, order = 'DESC') {
      this.orderby = orderby
      this.order = order
      this.page = 1
    },
  },
})
