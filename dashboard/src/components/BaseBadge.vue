<template>
  <span :class="['badge', badgeClass]">
    <span v-if="dot" class="w-1.5 h-1.5 rounded-full bg-current inline-block"></span>
    <slot>{{ label }}</slot>
  </span>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  label:  String,
  color:  { type: String, default: 'gray' }, // green, red, yellow, blue, gray, purple, navy
  dot:    { type: Boolean, default: true },
})

const badgeClass = computed(() => ({
  green:  'badge-green',
  red:    'badge-red',
  yellow: 'badge-yellow',
  blue:   'badge-blue',
  gray:   'badge-gray',
  purple: 'badge-purple',
  navy:   'badge bg-navy-100 text-navy-800',
})[props.color] || 'badge-gray')
</script>
