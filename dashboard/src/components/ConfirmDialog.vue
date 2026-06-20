<template>
  <BaseModal v-model="show" title="Konfirmasi Hapus" max-width="400px" persistent>
    <div class="flex gap-4">
      <div class="flex-shrink-0 w-10 h-10 rounded-full bg-red-100 flex items-center justify-center">
        <ExclamationTriangleIcon class="w-6 h-6 text-red-500" />
      </div>
      <div>
        <p class="text-gray-800 font-medium">{{ title }}</p>
        <p class="text-sm text-gray-500 mt-1">{{ message }}</p>
      </div>
    </div>

    <template #footer>
      <BaseButton variant="secondary" @click="cancel">Batal</BaseButton>
      <BaseButton variant="danger" :loading="loading" @click="confirm">
        Hapus
      </BaseButton>
    </template>
  </BaseModal>
</template>

<script setup>
import { ref } from 'vue'
import { ExclamationTriangleIcon } from '@heroicons/vue/24/outline'
import BaseModal from './BaseModal.vue'
import BaseButton from './BaseButton.vue'

const props = defineProps({
  title:   { type: String, default: 'Yakin ingin menghapus?' },
  message: { type: String, default: 'Data yang dihapus tidak dapat dikembalikan.' },
})

const emit = defineEmits(['confirm', 'cancel'])

const show    = ref(false)
const loading = ref(false)

function open()    { show.value = true }
function close()   { show.value = false; loading.value = false }
function cancel()  { close(); emit('cancel') }
async function confirm() {
  loading.value = true
  emit('confirm')
}

defineExpose({ open, close })
</script>
