<template>
  <DashboardLayout>
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
      <div>
        <h2 class="text-xl font-bold text-gray-900">Monitor Absensi Live</h2>
        <p class="text-sm text-gray-500">Pantau kehadiran siswa secara real-time</p>
      </div>
      <div class="flex items-center gap-2">
        <div class="flex items-center gap-1.5 text-sm text-emerald-600 font-medium">
          <div class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></div>
          Live (refresh 5s)
        </div>
      </div>
    </div>

    <!-- Pilih Sesi -->
    <div class="card mb-5">
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-end">
        <BaseSelect
          v-model="selectedScheduleId"
          label="Pilih Jadwal"
          id="mon-schedule"
          :options="scheduleOptions"
          placeholder="Pilih jadwal..."
        />
        <BaseInput
          v-model="sessionDate"
          label="Tanggal"
          id="mon-date"
          type="date"
        />
        <BaseButton variant="primary" @click="loadOrStartSession" :loading="loadingSession">
          <PlayIcon class="w-4 h-4" />
          {{ activeSession ? 'Refresh Sesi' : 'Buka Sesi' }}
        </BaseButton>
      </div>
    </div>

    <!-- Statistik sesi -->
    <div v-if="sessionData" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-5">
      <div v-for="stat in sessionStats" :key="stat.label"
           class="card p-3 text-center hover:shadow-md transition-shadow">
        <p class="text-2xl font-bold" :class="stat.color">{{ stat.count }}</p>
        <p class="text-xs text-gray-500 mt-0.5">{{ stat.label }}</p>
      </div>
    </div>

    <!-- Tabel siswa -->
    <div class="card p-0">
      <div class="flex items-center justify-between p-4 border-b border-gray-100">
        <h3 class="font-semibold text-gray-900">
          {{ sessionData?.session?.class_name || 'Daftar Siswa' }}
          <span v-if="sessionData" class="text-gray-400 font-normal text-sm ml-2">
            — {{ sessionData?.session?.subject_name }}
          </span>
        </h3>
        <div v-if="activeSession && sessionData?.session?.status === 'aktif'" class="flex gap-2">
          <BaseButton variant="danger" size="sm" @click="closeSession" :loading="closing">
            <StopIcon class="w-4 h-4" /> Tutup Sesi
          </BaseButton>
        </div>
      </div>

      <BaseTable
        :columns="attendanceColumns"
        :rows="sessionData?.attendances || []"
        :loading="loadingSession && !sessionData"
        empty-text="Pilih jadwal dan tanggal untuk memulai monitoring"
      >
        <template #cell-attendance_status="{ value }">
          <BaseBadge :color="statusColor(value)" :label="statusLabel(value)" :dot="true" />
        </template>

        <template #cell-scan_time="{ value }">
          <span class="font-mono text-sm text-gray-500">{{ formatTime(value) }}</span>
        </template>

        <template #actions="{ row }">
          <div v-if="row.attendance_id">
            <button
              class="btn btn-sm btn-ghost text-blue-600 hover:bg-blue-50 text-xs"
              @click="openUpdateStatus(row)"
            >
              <PencilSquareIcon class="w-3.5 h-3.5" /> Update
            </button>
          </div>
        </template>
      </BaseTable>
    </div>

    <!-- Modal update status -->
    <BaseModal v-model="showUpdateStatus" title="Update Status Absensi" max-width="380px">
      <div class="space-y-4">
        <p class="text-sm text-gray-600">
          Siswa: <strong>{{ updateTarget?.student_name }}</strong>
        </p>
        <BaseSelect
          v-model="newStatus"
          label="Status Baru"
          id="new-status"
          :options="statusOptions"
          placeholder="Pilih status"
        />
      </div>
      <template #footer>
        <BaseButton variant="secondary" @click="showUpdateStatus = false">Batal</BaseButton>
        <BaseButton variant="primary" :loading="updatingStatus" @click="doUpdateStatus">Simpan</BaseButton>
      </template>
    </BaseModal>
  </DashboardLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import BaseTable from '@/components/BaseTable.vue'
import BaseModal from '@/components/BaseModal.vue'
import BaseButton from '@/components/BaseButton.vue'
import BaseInput from '@/components/BaseInput.vue'
import BaseSelect from '@/components/BaseSelect.vue'
import BaseBadge from '@/components/BaseBadge.vue'
import { attendanceApi } from '@/api/attendanceApi'
import { schedulesApi } from '@/api/schedulesApi'
import { useUiStore } from '@/stores/uiStore'
import { PlayIcon, StopIcon, PencilSquareIcon } from '@heroicons/vue/24/outline'

const uiStore = useUiStore()

const schedules          = ref([])
const selectedScheduleId = ref('')
const sessionDate        = ref(new Date().toISOString().split('T')[0])
const activeSession      = ref(null)
const sessionData        = ref(null)
const loadingSession     = ref(false)
const closing            = ref(false)

// Update status
const showUpdateStatus = ref(false)
const updatingStatus   = ref(false)
const updateTarget     = ref(null)
const newStatus        = ref('')

const statusOptions = [
  { value: 'hadir',     label: 'Hadir' },
  { value: 'terlambat', label: 'Terlambat' },
  { value: 'izin',      label: 'Izin' },
  { value: 'sakit',     label: 'Sakit' },
  { value: 'alpha',     label: 'Alpha' },
]

const attendanceColumns = [
  { key: 'nis',               label: 'NIS',     width: '110px' },
  { key: 'student_name',      label: 'Nama Siswa' },
  { key: 'attendance_status', label: 'Status',  width: '110px' },
  { key: 'scan_time',         label: 'Waktu Scan', width: '110px' },
]

const scheduleOptions = computed(() =>
  schedules.value.map(s => ({
    value: String(s.id),
    label: `${s.day} | ${s.class_name} | ${s.subject_name} (${s.start_time?.substring(0,5)})`
  }))
)

const sessionStats = computed(() => {
  const stats = sessionData.value?.statistics || {}
  return [
    { label: 'Hadir',      count: stats.hadir     || 0, color: 'text-emerald-600' },
    { label: 'Terlambat',  count: stats.terlambat || 0, color: 'text-amber-600' },
    { label: 'Izin',       count: stats.izin      || 0, color: 'text-yellow-600' },
    { label: 'Sakit',      count: stats.sakit     || 0, color: 'text-blue-600' },
    { label: 'Alpha',      count: stats.alpha     || 0, color: 'text-red-600' },
    { label: 'Belum Scan', count: stats.belum_scan|| 0, color: 'text-gray-500' },
  ]
})

function statusColor(status) {
  return { hadir: 'green', alpha: 'red', izin: 'yellow', sakit: 'blue', terlambat: 'purple', belum_scan: 'gray' }[status] || 'gray'
}
function statusLabel(status) {
  return { hadir: 'Hadir', alpha: 'Alpha', izin: 'Izin', sakit: 'Sakit', terlambat: 'Terlambat', belum_scan: 'Belum Scan' }[status] || status
}
function formatTime(dt) {
  if (!dt) return '—'
  return new Date(dt).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' })
}

async function loadOrStartSession() {
  if (!selectedScheduleId.value) { uiStore.warning('Pilih jadwal terlebih dahulu.'); return }
  loadingSession.value = true
  try {
    // Coba buka sesi baru (jika sudah ada, backend akan return 409 dengan session_id)
    let sessionId = null
    try {
      const res = await attendanceApi.startSession({
        schedule_id: Number(selectedScheduleId.value),
        date: sessionDate.value
      })
      sessionId = res.data.data?.session_id
    } catch (err) {
      if (err.response?.status === 409) {
        sessionId = err.response.data?.data?.session_id
      } else throw err
    }
    activeSession.value = sessionId
    await refreshSession()
  } catch (err) {
    uiStore.error(err.response?.data?.message || 'Gagal membuka sesi.')
  } finally {
    loadingSession.value = false
  }
}

async function refreshSession() {
  if (!activeSession.value) return
  try {
    const res = await attendanceApi.sessionStatus(activeSession.value)
    sessionData.value = res.data.data
  } catch { /* silent */ }
}

async function closeSession() {
  closing.value = true
  try {
    await attendanceApi.closeSession({ session_id: activeSession.value })
    uiStore.success('Sesi berhasil ditutup. Auto-Alpha Engine telah dijalankan.')
    await refreshSession()
  } catch (err) {
    uiStore.error(err.response?.data?.message || 'Gagal menutup sesi.')
  } finally {
    closing.value = false
  }
}

function openUpdateStatus(row) {
  updateTarget.value = row
  newStatus.value = row.attendance_status !== 'belum_scan' ? row.attendance_status : ''
  showUpdateStatus.value = true
}

async function doUpdateStatus() {
  if (!newStatus.value) { uiStore.warning('Pilih status terlebih dahulu.'); return }
  updatingStatus.value = true
  try {
    await attendanceApi.updateStatus({
      attendance_id: updateTarget.value.attendance_id,
      status: newStatus.value
    })
    uiStore.success('Status absensi berhasil diperbarui.')
    showUpdateStatus.value = false
    await refreshSession()
  } catch (err) {
    uiStore.error(err.response?.data?.message || 'Gagal update status.')
  } finally {
    updatingStatus.value = false
  }
}

// Polling setiap 5 detik
let pollTimer = null
onMounted(async () => {
  const res = await schedulesApi.list({ limit: 100 })
  schedules.value = res.data?.data?.schedules || []
  pollTimer = setInterval(refreshSession, 5000)
})
onUnmounted(() => clearInterval(pollTimer))
</script>
