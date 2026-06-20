<template>
  <DashboardLayout>
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
      <div>
        <h2 class="text-xl font-bold text-gray-900">Laporan Kehadiran</h2>
        <p class="text-sm text-gray-500">Rekap absensi per kelas dan export ke Excel/PDF</p>
      </div>
    </div>

    <!-- Filter -->
    <div class="card mb-5">
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
        <BaseSelect
          v-model="filterClassId"
          label="Kelas"
          id="rep-class"
          :options="classOptions"
          placeholder="Pilih kelas"
        />
        <BaseInput v-model="startDate" label="Dari Tanggal" id="rep-start" type="date" />
        <BaseInput v-model="endDate"   label="Sampai Tanggal" id="rep-end" type="date" />
        <BaseButton variant="primary" @click="fetchReport" :loading="loading">
          <MagnifyingGlassIcon class="w-4 h-4" /> Tampilkan
        </BaseButton>
      </div>
    </div>

    <!-- Report card -->
    <div v-if="report" class="card p-0">
      <!-- Actions -->
      <div class="flex flex-wrap items-center justify-between gap-3 p-4 border-b border-gray-100">
        <div class="text-sm text-gray-600">
          <span class="font-semibold">{{ report.total_students }}</span> siswa ·
          <span class="font-semibold">{{ report.total_sessions }}</span> sesi ·
          Periode: {{ formatDate(startDate) }} – {{ formatDate(endDate) }}
        </div>
        <div class="flex gap-2">
          <BaseButton variant="success" size="sm" @click="exportExcel">
            <ArrowDownTrayIcon class="w-4 h-4" /> Export Excel
          </BaseButton>
          <BaseButton variant="danger" size="sm" @click="exportPdf">
            <DocumentIcon class="w-4 h-4" /> Export PDF
          </BaseButton>
        </div>
      </div>

      <!-- Tabel rekap -->
      <div class="overflow-x-auto">
        <table class="data-table">
          <thead>
            <tr>
              <th>No</th>
              <th>NIS</th>
              <th>Nama Siswa</th>
              <th class="text-center text-emerald-600">Hadir</th>
              <th class="text-center text-amber-600">Terlambat</th>
              <th class="text-center text-yellow-600">Izin</th>
              <th class="text-center text-blue-600">Sakit</th>
              <th class="text-center text-red-600">Alpha</th>
              <th class="text-center">Total</th>
              <th class="text-center">% Hadir</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(row, idx) in report.report" :key="row.student_id">
              <td class="text-gray-400 text-xs">{{ idx + 1 }}</td>
              <td class="font-mono text-sm">{{ row.nis }}</td>
              <td class="font-medium">{{ row.student_name }}</td>
              <td class="text-center">
                <span class="badge badge-green">{{ row.total_hadir }}</span>
              </td>
              <td class="text-center">
                <span class="badge badge-yellow">{{ row.total_terlambat }}</span>
              </td>
              <td class="text-center">
                <span class="badge badge-yellow">{{ row.total_izin }}</span>
              </td>
              <td class="text-center">
                <span class="badge badge-blue">{{ row.total_sakit }}</span>
              </td>
              <td class="text-center">
                <span class="badge badge-red">{{ row.total_alpha }}</span>
              </td>
              <td class="text-center font-semibold text-gray-700">{{ row.total_pertemuan }}</td>
              <td class="text-center">
                <span :class="['font-semibold text-sm', attendanceRate(row) >= 75 ? 'text-emerald-600' : 'text-red-500']">
                  {{ attendanceRate(row) }}%
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Empty state awal -->
    <div v-else-if="!loading" class="card flex flex-col items-center justify-center py-16 text-gray-400">
      <ChartBarIcon class="w-14 h-14 mb-3" />
      <p class="text-sm">Pilih kelas dan rentang tanggal, lalu klik Tampilkan</p>
    </div>
  </DashboardLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import * as XLSX from 'xlsx'
import { jsPDF } from 'jspdf'
import autoTable from 'jspdf-autotable'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import BaseInput from '@/components/BaseInput.vue'
import BaseSelect from '@/components/BaseSelect.vue'
import BaseButton from '@/components/BaseButton.vue'
import { attendanceApi } from '@/api/attendanceApi'
import { classesApi } from '@/api/classesApi'
import { useUiStore } from '@/stores/uiStore'
import {
  MagnifyingGlassIcon, ArrowDownTrayIcon, DocumentIcon, ChartBarIcon
} from '@heroicons/vue/24/outline'

const uiStore = useUiStore()
const loading = ref(false)
const classes = ref([])
const report  = ref(null)

// Defaults: bulan ini
const now = new Date()
const startDate = ref(`${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-01`)
const endDate   = ref(new Date(now.getFullYear(), now.getMonth() + 1, 0).toISOString().split('T')[0])
const filterClassId = ref('')

const classOptions = computed(() => classes.value.map(c => ({ value: String(c.id), label: c.name })))

function formatDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })
}

function attendanceRate(row) {
  const total = Number(row.total_pertemuan) || 0
  if (!total) return 0
  const hadir = Number(row.total_hadir) + Number(row.total_terlambat)
  return Math.round((hadir / total) * 100)
}

async function fetchReport() {
  if (!filterClassId.value) { uiStore.warning('Pilih kelas terlebih dahulu.'); return }
  if (!startDate.value || !endDate.value) { uiStore.warning('Isi rentang tanggal.'); return }
  loading.value = true
  try {
    const res = await attendanceApi.reportByClass({
      class_id:   filterClassId.value,
      start_date: startDate.value,
      end_date:   endDate.value,
    })
    report.value = res.data.data
  } catch (err) {
    uiStore.error(err.response?.data?.message || 'Gagal memuat laporan.')
  } finally {
    loading.value = false
  }
}

function exportExcel() {
  if (!report.value) return
  const rows = report.value.report.map((r, i) => ({
    'No':        i + 1,
    'NIS':       r.nis,
    'Nama':      r.student_name,
    'Hadir':     r.total_hadir,
    'Terlambat': r.total_terlambat,
    'Izin':      r.total_izin,
    'Sakit':     r.total_sakit,
    'Alpha':     r.total_alpha,
    'Total':     r.total_pertemuan,
    '% Hadir':   attendanceRate(r) + '%',
  }))
  const ws = XLSX.utils.json_to_sheet(rows)
  const wb = XLSX.utils.book_new()
  XLSX.utils.book_append_sheet(wb, ws, 'Laporan Absensi')
  XLSX.writeFile(wb, `laporan-absensi-${startDate.value}-${endDate.value}.xlsx`)
  uiStore.success('File Excel berhasil diunduh.')
}

function exportPdf() {
  if (!report.value) return
  const doc = new jsPDF({ orientation: 'landscape' })
  const className = classes.value.find(c => String(c.id) === filterClassId.value)?.name || ''

  doc.setFont('helvetica', 'bold')
  doc.setFontSize(14)
  doc.text('REKAP ABSENSI SISWA', 14, 16)
  doc.setFont('helvetica', 'normal')
  doc.setFontSize(10)
  doc.text(`Kelas: ${className}  |  Periode: ${formatDate(startDate.value)} – ${formatDate(endDate.value)}`, 14, 24)

  autoTable(doc, {
    startY: 30,
    head: [['No', 'NIS', 'Nama Siswa', 'Hadir', 'Terlambat', 'Izin', 'Sakit', 'Alpha', 'Total', '% Hadir']],
    body: report.value.report.map((r, i) => [
      i + 1, r.nis, r.student_name,
      r.total_hadir, r.total_terlambat, r.total_izin,
      r.total_sakit, r.total_alpha, r.total_pertemuan,
      attendanceRate(r) + '%'
    ]),
    styles: { fontSize: 9 },
    headStyles: { fillColor: [15, 35, 64], textColor: 255 },
    alternateRowStyles: { fillColor: [248, 250, 252] }
  })

  doc.save(`laporan-absensi-${startDate.value}-${endDate.value}.pdf`)
  uiStore.success('File PDF berhasil diunduh.')
}

onMounted(async () => {
  const res = await classesApi.listClasses()
  classes.value = res.data?.data?.classes || []
})
</script>
