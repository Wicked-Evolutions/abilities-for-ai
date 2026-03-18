<template>
  <span class="kl-tag">
    <span v-if="color" class="tag-dot" :style="{ background: color }"></span>
    {{ label }}
  </span>
</template>

<script setup>
defineProps({
  label: { type: String, required: true },
  color: { type: String, default: '' },
})
</script>
