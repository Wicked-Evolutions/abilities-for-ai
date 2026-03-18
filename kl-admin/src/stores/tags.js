import { defineStore } from 'pinia'
import { api } from '../api.js'

export const useTagsStore = defineStore('tags', {
  state: () => ({
    items: [],
    total: 0,
    loading: false,
    fetched: false,
  }),

  actions: {
    async fetchTags(force = false) {
      if (this.fetched && !force) return
      this.loading = true
      try {
        const data = await api.get('tags', { per_page: 100 })
        this.items = data.items || []
        this.total = data.total || 0
        this.fetched = true
      } finally {
        this.loading = false
      }
    },
  },
})
