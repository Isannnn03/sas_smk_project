<template>
  <button
    :type="type"
    :disabled="disabled || loading"
    :class="[baseClass, variantClass, sizeClass, 'transition-all duration-150']"
    v-bind="$attrs"
  >
    <svg v-if="loading" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
    </svg>
    <slot />
  </button>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  variant:  { type: String, default: 'primary' },
  size:     { type: String, default: 'md' },
  type:     { type: String, default: 'button' },
  loading:  Boolean,
  disabled: Boolean,
})

const baseClass = 'inline-flex items-center gap-2 font-semibold rounded-lg focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed active:scale-95'

const variantClass = computed(() => ({
  primary:   'bg-blue-600 text-white hover:bg-blue-700 focus:ring-blue-500',
  secondary: 'bg-gray-100 text-gray-700 hover:bg-gray-200 focus:ring-gray-400',
  danger:    'bg-red-500 text-white hover:bg-red-600 focus:ring-red-400',
  success:   'bg-emerald-500 text-white hover:bg-emerald-600 focus:ring-emerald-400',
  warning:   'bg-amber-500 text-white hover:bg-amber-600 focus:ring-amber-400',
  ghost:     'bg-transparent text-gray-600 hover:bg-gray-100 focus:ring-gray-300',
  navy:      'bg-navy-800 text-white hover:bg-navy-900 focus:ring-navy-700',
})[props.variant] || '')

const sizeClass = computed(() => ({
  xs: 'px-2.5 py-1.5 text-xs',
  sm: 'px-3 py-1.5 text-xs',
  md: 'px-4 py-2 text-sm',
  lg: 'px-6 py-3 text-base',
})[props.size] || 'px-4 py-2 text-sm')
</script>
