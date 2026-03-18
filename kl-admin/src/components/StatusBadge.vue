<template>
  <span class="kl-badge" :class="`badge-${value}`">{{ value }}</span>
</template>

<script setup>
defineProps({
  value: { type: String, required: true },
})
</script>
