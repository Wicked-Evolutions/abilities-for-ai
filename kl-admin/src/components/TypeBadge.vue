<template>
  <span class="kl-badge" :class="`badge-${type}`">{{ type }}</span>
</template>

<script setup>
defineProps({
  type: { type: String, required: true },
})
</script>
