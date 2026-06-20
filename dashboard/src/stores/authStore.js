import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { authApi } from '@/api/authApi'

export const useAuthStore = defineStore('auth', () => {
  const token = ref(null)
  const user  = ref(null)

  const isLoggedIn = computed(() => !!token.value)
  const isAdmin    = computed(() => user.value?.role === 'tata_usaha')

  // Restore session dari localStorage
  function restoreSession() {
    const storedToken = localStorage.getItem('sas_token')
    const storedUser  = localStorage.getItem('sas_user')
    if (storedToken && storedUser) {
      try {
        token.value = storedToken
        user.value  = JSON.parse(storedUser)
      } catch {
        logout()
      }
    }
  }

  async function login(credentials) {
    const { data: res } = await authApi.login(credentials)
    if (res.success) {
      token.value = res.data.token
      user.value  = res.data.user
      localStorage.setItem('sas_token', res.data.token)
      localStorage.setItem('sas_user', JSON.stringify(res.data.user))
      return { success: true }
    }
    return { success: false, message: res.message }
  }

  async function logout() {
    try {
      if (token.value) await authApi.logout()
    } catch { /* ignore */ } finally {
      token.value = null
      user.value  = null
      localStorage.removeItem('sas_token')
      localStorage.removeItem('sas_user')
    }
  }

  return { token, user, isLoggedIn, isAdmin, login, logout, restoreSession }
})
