<template>
  <RouterView />
  <ToastNotif />
</template>

<script setup>
import { onMounted } from 'vue'
import { RouterView } from 'vue-router'
import ToastNotif from '@/components/ToastNotif.vue'
import { useAuthStore } from '@/stores/authStore'

const authStore = useAuthStore()

onMounted(() => {
  authStore.restoreSession()
})
</script>
