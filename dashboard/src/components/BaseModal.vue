<template>
  <Teleport to="body">
    <Transition name="modal">
      <div v-if="modelValue" class="modal-overlay" @mousedown.self="onBackdrop">
        <div
          class="modal-box"
          :style="maxWidth ? `max-width: ${maxWidth}` : ''"
          role="dialog"
          :aria-label="title"
        >
          <!-- Header -->
          <div class="modal-header">
            <h3 class="text-base font-semibold text-gray-900">{{ title }}</h3>
            <button
              v-if="!persistent"
              @click="$emit('update:modelValue', false)"
              class="btn-icon btn-ghost text-gray-400 hover:text-gray-600 rounded-lg"
            >
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>

          <!-- Body -->
          <div class="modal-body max-h-[70vh] overflow-y-auto">
            <slot />
          </div>

          <!-- Footer slot -->
          <div v-if="$slots.footer" class="modal-footer">
            <slot name="footer" />
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { XMarkIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  modelValue: Boolean,
  title:      { type: String, default: '' },
  maxWidth:   { type: String, default: '' },
  persistent: { type: Boolean, default: false }
})

const emit = defineEmits(['update:modelValue'])

function onBackdrop() {
  if (!props.persistent) emit('update:modelValue', false)
}
</script>

<style scoped>
.modal-enter-active, .modal-leave-active {
  transition: opacity 0.2s ease;
}
.modal-enter-active .modal-box,
.modal-leave-active .modal-box {
  transition: transform 0.2s ease, opacity 0.2s ease;
}
.modal-enter-from, .modal-leave-to {
  opacity: 0;
}
.modal-enter-from .modal-box {
  transform: translateY(-16px) scale(0.97);
  opacity: 0;
}
.modal-leave-to .modal-box {
  transform: translateY(8px) scale(0.97);
  opacity: 0;
}
</style>
