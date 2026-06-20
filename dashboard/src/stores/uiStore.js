import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useUiStore = defineStore('ui', () => {
  const toasts   = ref([])
  const loading  = ref(false)
  let   toastId  = 0

  function showToast(message, type = 'success', duration = 3500) {
    const id = ++toastId
    toasts.value.push({ id, message, type })
    setTimeout(() => removeToast(id), duration)
  }

  function removeToast(id) {
    const idx = toasts.value.findIndex(t => t.id === id)
    if (idx !== -1) toasts.value.splice(idx, 1)
  }

  function success(msg)  { showToast(msg, 'success') }
  function error(msg)    { showToast(msg, 'error', 5000) }
  function warning(msg)  { showToast(msg, 'warning') }
  function info(msg)     { showToast(msg, 'info') }

  function setLoading(val) { loading.value = val }

  return { toasts, loading, showToast, removeToast, success, error, warning, info, setLoading }
})
