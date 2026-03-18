<template>
  <div v-if="totalPages > 1" class="kl-pagination">
    <span class="kl-pagination-info">
      {{ start }}–{{ end }} of {{ total }}
    </span>
    <div class="kl-pagination-pages">
      <button
        v-for="p in totalPages"
        :key="p"
        class="kl-page-btn"
        :class="{ active: p === page }"
        @click="$emit('update:page', p)"
      >
        {{ p }}
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  page: { type: Number, required: true },
  perPage: { type: Number, required: true },
  total: { type: Number, required: true },
})

defineEmits(['update:page'])

const totalPages = computed(() => Math.ceil(props.total / props.perPage))
const start = computed(() => (props.page - 1) * props.perPage + 1)
const end = computed(() => Math.min(props.page * props.perPage, props.total))
</script>
