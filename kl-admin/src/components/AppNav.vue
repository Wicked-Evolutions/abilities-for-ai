<template>
  <aside class="kl-sidebar">
    <div class="kl-sidebar-brand">
      <h2>Abilities for AI</h2>
      <h1><span class="dot"></span>Knowledge Layer</h1>
    </div>
    <nav class="kl-nav">
      <router-link
        v-for="item in navItems"
        :key="item.to"
        :to="item.to"
        class="kl-nav-item"
        :class="{ active: isActive(item) }"
      >
        <span class="kl-nav-icon">{{ item.icon }}</span>
        {{ item.label }}
      </router-link>
    </nav>
    <div class="kl-theme-toggle">
      <button
        class="kl-theme-btn"
        :class="{ active: theme === 'dark' }"
        @click="$emit('toggle-theme')"
      >
        {{ theme === 'dark' ? '☀ Light' : '🌙 Dark' }}
      </button>
    </div>
    <div class="kl-sidebar-footer">
      v{{ version }}
    </div>
  </aside>
</template>

<script setup>
import { useRoute } from 'vue-router'

defineProps({
  theme: { type: String, default: 'dark' },
})

defineEmits(['toggle-theme'])

const route = useRoute()
const version = window.abilitiesKL?.version || '0.1.0'

const navItems = [
  { to: '/documents', label: 'Documents', icon: '◧', match: '/documents' },
  { to: '/sessions', label: 'Sessions', icon: '▤', match: '/sessions' },
  { to: '/activity', label: 'Activity', icon: '◷', match: '/activity' },
  { to: '/observations', label: 'Observations', icon: '◉', match: '/observations' },
  { to: '/tags', label: 'Tags', icon: '⬡', match: '/tags' },
  { to: '/dashboard', label: 'Dashboard', icon: '◫', match: '/dashboard' },
]

function isActive(item) {
  return route.path.startsWith(item.match)
}
</script>
