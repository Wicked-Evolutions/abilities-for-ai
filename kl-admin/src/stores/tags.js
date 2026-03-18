import { defineStore } from 'pinia'
import { api } from '../api.js'

export const useTagsStore = defineStore('tags', {
  state: () => ({
    items: [],
    total: 0,
    page: 1,
    perPage: 50,
    loading: false,
  }),

  actions: {
    async fetchTags() {
      this.loading = true
      try {
        const data = await api.get('tags', { page: this.page, per_page: this.perPage })
        this.items = data.items || []
        this.total = data.total || 0
      } finally {
        this.loading = false
      }
    },
  },
})
