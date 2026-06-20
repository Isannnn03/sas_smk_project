<template>
  <DashboardLayout>
    <!-- ── Stat Cards ── -->
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5 mb-6">
      <div v-for="stat in stats" :key="stat.key" class="stat-card">
        <div :class="['flex-shrink-0 w-12 h-12 rounded-xl flex items-center justify-center', stat.bg]">
          <component :is="stat.icon" :class="['w-6 h-6', stat.iconColor]" />
        </div>
        <div>
          <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">{{ stat.label }}</p>
          <p class="text-2xl font-bold text-gray-900 mt-0.5">
            <span v-if="loadingStats" class="inline-block w-12 h-6 bg-gray-100 rounded animate-pulse"></span>
            <span v-else>{{ stat.value }}</span>
          </p>
          <p class="text-xs text-gray-400 mt-0.5">{{ stat.sub }}</p>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
      <!-- ── Grafik Kehadiran Mingguan ── -->
      <div class="card lg:col-span-2">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-base font-semibold text-gray-900">Kehadiran 7 Hari Terakhir</h2>
          <span class="badge badge-blue">Mingguan</span>
        </div>
        <div class="h-56">
          <Bar v-if="chartReady" :data="chartData" :options="chartOptions" />
          <div v-else class="h-full flex items-center justify-center">
            <div class="w-8 h-8 border-2 border-blue-500 border-t-transparent rounded-full animate-spin"></div>
          </div>
        </div>
      </div>

      <!-- ── Scan Terbaru ── -->
      <div class="card">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-base font-semibold text-gray-900">Scan Terbaru</h2>
          <div class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></div>
        </div>

        <div v-if="loadingRecent" class="space-y-3">
          <div v-for="n in 5" :key="n" class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-full bg-gray-100 animate-pulse"></div>
            <div class="flex-1 space-y-1.5">
              <div class="h-3 bg-gray-100 rounded animate-pulse w-3/4"></div>
              <div class="h-2.5 bg-gray-100 rounded animate-pulse w-1/2"></div>
            </div>
          </div>
        </div>

        <div v-else-if="!recentScans.length" class="text-center py-8 text-gray-400 text-sm">
          Belum ada scan hari ini
        </div>

        <div v-else class="space-y-3 overflow-y-auto max-h-52">
          <div v-for="scan in recentScans" :key="scan.attendance_id"
               class="flex items-center gap-3 animate-fade-in">
            <div class="flex-shrink-0 w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center text-blue-700 font-semibold text-xs">
              {{ scan.student_name?.substring(0,2).toUpperCase() }}
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-gray-900 truncate">{{ scan.student_name }}</p>
              <p class="text-xs text-gray-400">{{ scan.class_name }} · {{ formatTime(scan.scan_time) }}</p>
            </div>
            <BaseBadge :color="statusColor(scan.attendance_status)" :label="scan.attendance_status" />
          </div>
        </div>
      </div>
    </div>

    <!-- ── Sesi Aktif Hari Ini ── -->
    <div class="card mt-5">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-base font-semibold text-gray-900">Sesi Absensi Hari Ini</h2>
        <RouterLink to="/attendance/monitor" class="text-sm text-blue-600 font-medium hover:underline">
          Lihat semua →
        </RouterLink>
      </div>
      <p v-if="loadingStats" class="text-sm text-gray-400">Memuat...</p>
      <p v-else-if="!todaySessions.length" class="text-sm text-gray-400">Belum ada sesi hari ini.</p>
      <div v-else class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3">
        <div v-for="s in todaySessions" :key="s.id"
             class="flex items-center gap-3 p-3 rounded-xl border border-gray-100 hover:border-blue-200 hover:bg-blue-50/40 transition-colors">
          <div class="flex-shrink-0 w-9 h-9 rounded-lg bg-navy-800 flex items-center justify-center">
            <CalendarDaysIcon class="w-5 h-5 text-white" />
          </div>
          <div class="min-w-0">
            <p class="text-sm font-medium text-gray-900 truncate">{{ s.class_name }} — {{ s.subject_name }}</p>
            <p class="text-xs text-gray-400">{{ s.teacher_name }}</p>
          </div>
          <BaseBadge :color="s.status === 'aktif' ? 'green' : 'gray'" :label="s.status" class="ml-auto flex-shrink-0" />
        </div>
      </div>
    </div>
  </DashboardLayout>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { RouterLink } from 'vue-router'
import { Bar } from 'vue-chartjs'
import {
  Chart as ChartJS, CategoryScale, LinearScale,
  BarElement, Title, Tooltip, Legend
} from 'chart.js'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import BaseBadge from '@/components/BaseBadge.vue'
import { studentsApi } from '@/api/studentsApi'
import { schedulesApi } from '@/api/schedulesApi'
import { attendanceApi } from '@/api/attendanceApi'
import { teachersApi } from '@/api/teachersApi'
import {
  UsersIcon, AcademicCapIcon, CalendarDaysIcon, CheckCircleIcon
} from '@heroicons/vue/24/outline'

ChartJS.register(CategoryScale, LinearScale, BarElement, Title, Tooltip, Legend)

const loadingStats  = ref(true)
const loadingRecent = ref(true)
const chartReady    = ref(false)

const totalStudents  = ref(0)
const totalTeachers  = ref(0)
const todayPresent   = ref(0)
const totalSessions  = ref(0)
const recentScans    = ref([])
const todaySessions  = ref([])

const stats = computed(() => [
  {
    key: 'students', label: 'Total Siswa', value: totalStudents.value,
    sub: 'Siswa aktif', icon: UsersIcon,
    bg: 'bg-blue-100', iconColor: 'text-blue-600'
  },
  {
    key: 'teachers', label: 'Total Guru', value: totalTeachers.value,
    sub: 'Pengajar aktif', icon: AcademicCapIcon,
    bg: 'bg-purple-100', iconColor: 'text-purple-600'
  },
  {
    key: 'present', label: 'Hadir Hari Ini', value: todayPresent.value,
    sub: 'Dari total scan', icon: CheckCircleIcon,
    bg: 'bg-emerald-100', iconColor: 'text-emerald-600'
  },
  {
    key: 'sessions', label: 'Sesi Aktif', value: totalSessions.value,
    sub: 'Hari ini', icon: CalendarDaysIcon,
    bg: 'bg-amber-100', iconColor: 'text-amber-600'
  }
])

// ── Chart data (simulasi data mingguan) ──
const chartData = ref({
  labels: ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Hari Ini'],
  datasets: [
    {
      label: 'Hadir',
      data: [0, 0, 0, 0, 0, 0, 0],
      backgroundColor: 'rgba(59,130,246,0.8)',
      borderRadius: 6,
    },
    {
      label: 'Alpha',
      data: [0, 0, 0, 0, 0, 0, 0],
      backgroundColor: 'rgba(239,68,68,0.7)',
      borderRadius: 6,
    }
  ]
})

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      position: 'top',
      labels: { usePointStyle: true, boxWidth: 8, font: { size: 12 } }
    }
  },
  scales: {
    y: {
      beginAtZero: true,
      grid: { color: 'rgba(0,0,0,0.04)' },
      ticks: { precision: 0 }
    },
    x: {
      grid: { display: false }
    }
  }
}

function formatTime(dt) {
  if (!dt) return '—'
  return new Date(dt).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })
}

function statusColor(status) {
  return { hadir: 'green', alpha: 'red', izin: 'yellow', sakit: 'blue', terlambat: 'purple', belum_scan: 'gray' }[status] || 'gray'
}

async function loadStats() {
  loadingStats.value = true
  try {
    const [studRes, schedRes, teachRes] = await Promise.allSettled([
      studentsApi.list({ limit: 1 }),
      schedulesApi.list({ limit: 1 }),
      teachersApi.list({ limit: 1 })
    ])

    if (studRes.status === 'fulfilled') {
      totalStudents.value = studRes.value.data?.data?.pagination?.total_items || 0
    }

    if (teachRes.status === 'fulfilled') {
      totalTeachers.value = teachRes.value.data?.data?.pagination?.total_items || 0
    }
  } catch { /* silent */ } finally {
    loadingStats.value = false
  }
}

async function loadChartData() {
  // Dalam implementasi nyata, ganti dengan call API: const res = await attendanceApi.dashboardStats()
  const days = 7
  const hadirData  = Array.from({ length: days }, () => Math.floor(Math.random() * 30) + 10)
  const alphaData  = Array.from({ length: days }, () => Math.floor(Math.random() * 10))
  
  chartData.value = {
    ...chartData.value,
    datasets: [
      { ...chartData.value.datasets[0], data: hadirData },
      { ...chartData.value.datasets[1], data: alphaData }
    ]
  }
  chartReady.value = true
}

async function loadRecentScans() {
  loadingRecent.value = true
  try {
    // Ambil sesi aktif pertama yang ada, kemudian ambil statusnya
    const res = await schedulesApi.list({ limit: 5 })
    const schedules = res.data?.data?.schedules || []
    todaySessions.value = schedules.slice(0, 6).map(s => ({
      id: s.id,
      class_name: s.class_name,
      subject_name: s.subject_name,
      teacher_name: s.teacher_name,
      status: 'aktif'
    }))
    totalSessions.value = todaySessions.value.length
  } catch { /* silent */ } finally {
    loadingRecent.value = false
  }
}

onMounted(async () => {
  await Promise.all([loadStats(), loadChartData(), loadRecentScans()])
})
</script>
