<template>
  <Transition name="toast-list" tag="div">
    <div class="fixed top-4 right-4 z-[9999] flex flex-col gap-2 w-80">
      <TransitionGroup name="toast">
        <div
          v-for="toast in uiStore.toasts"
          :key="toast.id"
          class="flex items-start gap-3 px-4 py-3.5 rounded-xl shadow-lg border text-sm font-medium cursor-pointer select-none"
          :class="toastClass(toast.type)"
          @click="uiStore.removeToast(toast.id)"
        >
          <component :is="toastIcon(toast.type)" class="w-5 h-5 flex-shrink-0 mt-0.5" />
          <span>{{ toast.message }}</span>
        </div>
      </TransitionGroup>
    </div>
  </Transition>
</template>

<script setup>
import { useUiStore } from '@/stores/uiStore'
import {
  CheckCircleIcon,
  XCircleIcon,
  ExclamationTriangleIcon,
  InformationCircleIcon
} from '@heroicons/vue/24/solid'

const uiStore = useUiStore()

function toastClass(type) {
  return {
    success: 'bg-emerald-50 border-emerald-200 text-emerald-800',
    error:   'bg-red-50 border-red-200 text-red-800',
    warning: 'bg-amber-50 border-amber-200 text-amber-800',
    info:    'bg-blue-50 border-blue-200 text-blue-800',
  }[type] || 'bg-white border-gray-200 text-gray-800'
}

function toastIcon(type) {
  return {
    success: CheckCircleIcon,
    error:   XCircleIcon,
    warning: ExclamationTriangleIcon,
    info:    InformationCircleIcon,
  }[type] || InformationCircleIcon
}
</script>

<style scoped>
.toast-enter-active, .toast-leave-active {
  transition: all 0.25s ease;
}
.toast-enter-from {
  opacity: 0;
  transform: translateX(100%);
}
.toast-leave-to {
  opacity: 0;
  transform: translateX(100%);
}
</style>
