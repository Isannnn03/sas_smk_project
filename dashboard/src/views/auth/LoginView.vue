<template>
  <AuthLayout>
    <div class="w-full max-w-md">
      <!-- Logo card -->
      <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-16 h-16 bg-blue-500 rounded-2xl shadow-lg shadow-blue-500/40 mb-4">
          <AcademicCapIcon class="w-8 h-8 text-white" />
        </div>
        <h1 class="text-2xl font-bold text-white">SAS-SMK</h1>
        <p class="text-navy-300 text-sm mt-1">Smart Attendance System</p>
      </div>

      <!-- Login card -->
      <div class="bg-white/10 backdrop-blur-md border border-white/20 rounded-2xl p-8 shadow-2xl">
        <h2 class="text-lg font-semibold text-white mb-6">Masuk ke Dashboard</h2>

        <form @submit.prevent="handleLogin" class="space-y-5">
          <!-- Username -->
          <div>
            <label class="block text-sm font-medium text-navy-200 mb-1.5">Username</label>
            <div class="relative">
              <UserIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-navy-400" />
              <input
                v-model="form.username"
                type="text"
                id="username"
                placeholder="Masukkan username"
                autocomplete="username"
                :class="[
                  'w-full pl-10 pr-4 py-3 rounded-xl border text-sm transition duration-150',
                  'bg-white/10 border-white/20 text-white placeholder-navy-400',
                  'focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-blue-400',
                  errors.username ? 'border-red-400' : ''
                ]"
              />
            </div>
            <p v-if="errors.username" class="text-red-400 text-xs mt-1">{{ errors.username }}</p>
          </div>

          <!-- Password -->
          <div>
            <label class="block text-sm font-medium text-navy-200 mb-1.5">Password</label>
            <div class="relative">
              <LockClosedIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-navy-400" />
              <input
                v-model="form.password"
                :type="showPw ? 'text' : 'password'"
                id="password"
                placeholder="Masukkan password"
                autocomplete="current-password"
                :class="[
                  'w-full pl-10 pr-10 py-3 rounded-xl border text-sm transition duration-150',
                  'bg-white/10 border-white/20 text-white placeholder-navy-400',
                  'focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-blue-400',
                  errors.password ? 'border-red-400' : ''
                ]"
              />
              <button
                type="button"
                @click="showPw = !showPw"
                class="absolute right-3 top-1/2 -translate-y-1/2 text-navy-400 hover:text-white transition-colors"
              >
                <EyeIcon v-if="!showPw" class="w-4 h-4" />
                <EyeSlashIcon v-else class="w-4 h-4" />
              </button>
            </div>
            <p v-if="errors.password" class="text-red-400 text-xs mt-1">{{ errors.password }}</p>
          </div>

          <!-- Error server -->
          <div v-if="serverError"
               class="flex items-center gap-2 bg-red-500/20 border border-red-500/30 rounded-xl px-4 py-3 text-red-300 text-sm">
            <ExclamationCircleIcon class="w-5 h-5 flex-shrink-0" />
            {{ serverError }}
          </div>

          <!-- Submit -->
          <button
            type="submit"
            :disabled="loading"
            class="w-full py-3 rounded-xl bg-blue-500 hover:bg-blue-600 active:scale-[0.98]
                   text-white font-semibold text-sm transition-all duration-150
                   focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-2 focus:ring-offset-transparent
                   disabled:opacity-60 disabled:cursor-not-allowed flex items-center justify-center gap-2"
          >
            <svg v-if="loading" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
            </svg>
            {{ loading ? 'Memproses...' : 'Masuk' }}
          </button>
        </form>

        <p class="text-center text-navy-400 text-xs mt-6">
          Smart Attendance System © {{ new Date().getFullYear() }}
        </p>
      </div>
    </div>
  </AuthLayout>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/authStore'
import { useUiStore } from '@/stores/uiStore'
import AuthLayout from '@/layouts/AuthLayout.vue'
import {
  AcademicCapIcon, UserIcon, LockClosedIcon,
  EyeIcon, EyeSlashIcon, ExclamationCircleIcon
} from '@heroicons/vue/24/outline'

const authStore = useAuthStore()
const uiStore   = useUiStore()
const router    = useRouter()
const route     = useRoute()

const form = reactive({ username: '', password: '' })
const errors = reactive({ username: '', password: '' })
const serverError = ref('')
const loading     = ref(false)
const showPw      = ref(false)

function validate() {
  errors.username = form.username.trim() ? '' : 'Username wajib diisi'
  errors.password = form.password       ? '' : 'Password wajib diisi'
  return !errors.username && !errors.password
}

async function handleLogin() {
  serverError.value = ''
  if (!validate()) return

  loading.value = true
  try {
    const result = await authStore.login({
      username: form.username.trim(),
      password: form.password,
      source: 'dashboard'
    })

    if (result.success) {
      uiStore.success(`Selamat datang, ${authStore.user?.username}!`)
      const redirect = route.query.redirect || '/dashboard'
      router.push(redirect)
    } else {
      serverError.value = result.message || 'Login gagal.'
    }
  } catch (err) {
    serverError.value = err.response?.data?.message || 'Terjadi kesalahan server.'
  } finally {
    loading.value = false
  }
}
</script>
