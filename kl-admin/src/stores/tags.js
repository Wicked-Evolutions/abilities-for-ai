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
        this.items = Array.isArray(data) ? data : (data.items || [])
        this.total = Array.isArray(data) ? data.length : (data.total || 0)
        this.fetched = true
      } finally {
        this.loading = false
      }
    },

    async createTag(data) {
      const tag = await api.post('tags', data)
      this.fetched = false
      await this.fetchTags(true)
      return tag
    },

    async updateTag(id, data) {
      const tag = await api.put(`tags/${id}`, data)
      this.fetched = false
      await this.fetchTags(true)
      return tag
    },

    async deleteTag(id) {
      await api.del(`tags/${id}`)
      this.fetched = false
      await this.fetchTags(true)
    },
  },
})
