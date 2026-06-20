<template>
  <div class="flex h-screen overflow-hidden bg-gray-50">
    <!-- ── Sidebar ── -->
    <aside
      class="flex-shrink-0 flex flex-col bg-navy-900 transition-all duration-300 ease-in-out overflow-hidden"
      :class="sidebarOpen ? 'w-64' : 'w-16'"
    >
      <!-- Logo -->
      <div class="flex items-center gap-3 px-4 py-5 border-b border-white/10 min-h-[68px]">
        <div class="flex-shrink-0 w-8 h-8 bg-blue-500 rounded-lg flex items-center justify-center">
          <AcademicCapIcon class="w-5 h-5 text-white" />
        </div>
        <Transition name="fade-text">
          <div v-show="sidebarOpen" class="overflow-hidden whitespace-nowrap">
            <p class="text-white font-bold text-sm leading-tight">SAS-SMK</p>
            <p class="text-navy-300 text-xs">Tata Usaha</p>
          </div>
        </Transition>
      </div>

      <!-- Navigation -->
      <nav class="flex-1 overflow-y-auto px-2 py-4 space-y-1">
        <template v-for="item in navItems" :key="item.name">
          <RouterLink
            :to="item.to"
            class="sidebar-link group relative"
            :class="{ active: isActive(item) }"
          >
            <component :is="item.icon" class="w-5 h-5 flex-shrink-0" />
            <Transition name="fade-text">
              <span v-show="sidebarOpen" class="overflow-hidden whitespace-nowrap text-sm">
                {{ item.label }}
              </span>
            </Transition>
            <!-- Tooltip saat sidebar collapsed -->
            <div v-if="!sidebarOpen"
                 class="absolute left-full ml-2 px-2 py-1 bg-gray-900 text-white text-xs rounded-md
                        opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap z-50 pointer-events-none">
              {{ item.label }}
            </div>
          </RouterLink>
        </template>
      </nav>

      <!-- User info -->
      <div class="px-2 pb-4 border-t border-white/10 pt-4">
        <div class="flex items-center gap-3 px-3 py-2 rounded-lg">
          <div class="flex-shrink-0 w-8 h-8 rounded-full bg-blue-500 flex items-center justify-center text-white font-semibold text-xs">
            {{ userInitials }}
          </div>
          <Transition name="fade-text">
            <div v-show="sidebarOpen" class="overflow-hidden whitespace-nowrap flex-1 min-w-0">
              <p class="text-white text-sm font-medium truncate">{{ authStore.user?.username }}</p>
              <p class="text-navy-400 text-xs capitalize">{{ authStore.user?.role }}</p>
            </div>
          </Transition>
        </div>
        <button
          @click="handleLogout"
          class="mt-2 sidebar-link w-full text-red-400 hover:bg-red-500/20 hover:text-red-300"
        >
          <ArrowRightOnRectangleIcon class="w-5 h-5 flex-shrink-0" />
          <Transition name="fade-text">
            <span v-show="sidebarOpen" class="overflow-hidden whitespace-nowrap text-sm">Logout</span>
          </Transition>
        </button>
      </div>
    </aside>

    <!-- ── Main content ── -->
    <div class="flex-1 flex flex-col overflow-hidden">
      <!-- Topbar -->
      <header class="flex-shrink-0 h-[68px] bg-white border-b border-gray-100 flex items-center justify-between px-6 shadow-sm">
        <div class="flex items-center gap-4">
          <button
            @click="sidebarOpen = !sidebarOpen"
            class="btn-icon btn-ghost rounded-lg"
          >
            <Bars3Icon class="w-5 h-5 text-gray-500" />
          </button>
          <div>
            <h1 class="text-lg font-semibold text-gray-900">{{ currentPageTitle }}</h1>
            <p class="text-xs text-gray-400">{{ formattedDate }}</p>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <div class="w-8 h-8 rounded-full bg-blue-500 flex items-center justify-center text-white font-semibold text-xs">
            {{ userInitials }}
          </div>
        </div>
      </header>

      <!-- Page content -->
      <main class="flex-1 overflow-y-auto p-6">
        <slot />
      </main>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/authStore'
import { useUiStore } from '@/stores/uiStore'
import {
  HomeIcon, UsersIcon, AcademicCapIcon, BookOpenIcon,
  CalendarDaysIcon, EyeIcon, ChartBarIcon,
  Bars3Icon, ArrowRightOnRectangleIcon
} from '@heroicons/vue/24/outline'

const authStore = useAuthStore()
const uiStore   = useUiStore()
const route     = useRoute()
const router    = useRouter()

const sidebarOpen = ref(true)

const navItems = [
  { name: 'dashboard',  label: 'Dashboard',        to: '/dashboard',          icon: HomeIcon },
  { name: 'students',   label: 'Data Siswa',        to: '/students',           icon: UsersIcon },
  { name: 'teachers',   label: 'Data Guru',         to: '/teachers',           icon: AcademicCapIcon },
  { name: 'classes',    label: 'Kelas & Mapel',     to: '/classes',            icon: BookOpenIcon },
  { name: 'schedules',  label: 'Jadwal Pelajaran',  to: '/schedules',          icon: CalendarDaysIcon },
  { name: 'monitor',    label: 'Monitor Absensi',   to: '/attendance/monitor', icon: EyeIcon },
  { name: 'report',     label: 'Laporan',           to: '/attendance/report',  icon: ChartBarIcon },
]

function isActive(item) {
  return route.path.startsWith(item.to)
}

const currentPageTitle = computed(() => route.meta.title || 'Dashboard')

const userInitials = computed(() => {
  const name = authStore.user?.username || 'U'
  return name.substring(0, 2).toUpperCase()
})

const formattedDate = computed(() => {
  return new Date().toLocaleDateString('id-ID', {
    weekday: 'long', day: 'numeric', month: 'long', year: 'numeric'
  })
})

async function handleLogout() {
  await authStore.logout()
  router.push('/login')
  uiStore.info('Anda telah logout.')
}
</script>

<style scoped>
.fade-text-enter-active, .fade-text-leave-active {
  transition: opacity 0.15s ease;
}
.fade-text-enter-from, .fade-text-leave-to {
  opacity: 0;
}
</style>
