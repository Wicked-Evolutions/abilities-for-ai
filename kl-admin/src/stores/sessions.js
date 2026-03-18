import { defineStore } from 'pinia'
import { api } from '../api.js'

export const useSessionsStore = defineStore('sessions', {
  state: () => ({
    items: [],
    total: 0,
    page: 1,
    perPage: 20,
    loading: false,
  }),

  actions: {
    async fetchSessions() {
      this.loading = true
      try {
        const data = await api.get('sessions', { page: this.page, per_page: this.perPage })
        this.items = data.items || []
        this.total = data.total || 0
      } finally {
        this.loading = false
      }
    },
  },
})
