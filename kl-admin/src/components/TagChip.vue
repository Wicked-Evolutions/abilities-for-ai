<template>
  <span class="kl-tag" @click="$emit('click')">
    <span v-if="color" class="tag-dot" :style="{ background: color }"></span>
    {{ label }}
    <span v-if="removable" class="tag-remove" @click.stop="$emit('remove')">&times;</span>
  </span>
</template>

<script setup>
defineProps({
  label: { type: String, required: true },
  color: { type: String, default: '' },
  removable: { type: Boolean, default: false },
})

defineEmits(['click', 'remove'])
</script>
