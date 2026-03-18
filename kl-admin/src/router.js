import { createRouter, createWebHashHistory } from 'vue-router'

const routes = [
  { path: '/', redirect: '/documents' },
  { path: '/dashboard', name: 'dashboard', component: () => import('./views/Dashboard.vue') },
  { path: '/documents', name: 'documents', component: () => import('./views/Documents.vue') },
  { path: '/documents/:id', name: 'document-detail', component: () => import('./views/DocumentDetail.vue') },
  { path: '/sessions', name: 'sessions', component: () => import('./views/Sessions.vue') },
  { path: '/observations', name: 'observations', component: () => import('./views/Observations.vue') },
  { path: '/tags', name: 'tags', component: () => import('./views/Tags.vue') },
]

export default createRouter({
  history: createWebHashHistory(),
  routes,
})
