<template>
  <DashboardLayout>
    <!-- Header bar -->
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
      <div>
        <h2 class="text-xl font-bold text-gray-900">Data Siswa</h2>
        <p class="text-sm text-gray-500">Kelola data siswa, QR Code, dan kartu pelajar</p>
      </div>
      <div class="flex gap-2">
        <BaseButton variant="secondary" @click="showImport = true">
          <ArrowUpTrayIcon class="w-4 h-4" /> Import Excel
        </BaseButton>
        <BaseButton variant="primary" @click="openCreate">
          <PlusIcon class="w-4 h-4" /> Tambah Siswa
        </BaseButton>
      </div>
    </div>

    <!-- Card tabel -->
    <div class="card p-0">
      <!-- Filter bar -->
      <div class="flex flex-wrap items-center gap-3 p-4 border-b border-gray-100">
        <div class="relative flex-1 min-w-48">
          <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
          <input
            v-model="search"
            type="text"
            placeholder="Cari nama / NIS..."
            class="form-input pl-9"
            @input="debouncedFetch"
          />
        </div>
        <select v-model="filterClass" class="form-select w-48" @change="fetchStudents">
          <option value="">Semua Kelas</option>
          <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>
        <select v-model="filterStatus" class="form-select w-36" @change="fetchStudents">
          <option value="">Semua Status</option>
          <option value="aktif">Aktif</option>
          <option value="non_aktif">Non-Aktif</option>
        </select>
      </div>

      <!-- Table -->
      <BaseTable
        :columns="columns"
        :rows="filteredStudents"
        :loading="loading"
        :pagination="pagination"
        empty-text="Belum ada data siswa"
        @page-change="(p) => { currentPage = p; fetchStudents() }"
      >
        <template #cell-status="{ value }">
          <BaseBadge :color="value === 'aktif' ? 'green' : 'gray'" :label="value" />
        </template>

        <template #cell-qr_code="{ value }">
          <BaseBadge v-if="value" color="blue" label="Ada QR" />
          <BaseBadge v-else color="red" label="Belum ada" />
        </template>

        <template #actions="{ row }">
          <div class="flex items-center justify-end gap-1">
            <button
              class="btn btn-sm btn-ghost text-emerald-600 hover:bg-emerald-50"
              title="Cetak Kartu"
              @click="printCard(row)"
            >
              <PrinterIcon class="w-4 h-4" />
            </button>
            <button
              class="btn btn-sm btn-ghost text-blue-600 hover:bg-blue-50"
              title="Generate QR"
              @click="generateQr(row)"
            >
              <QrCodeIcon class="w-4 h-4" />
            </button>
            <button
              class="btn btn-sm btn-ghost text-amber-600 hover:bg-amber-50"
              title="Edit"
              @click="openEdit(row)"
            >
              <PencilIcon class="w-4 h-4" />
            </button>
            <button
              class="btn btn-sm btn-ghost text-red-600 hover:bg-red-50"
              title="Hapus"
              @click="confirmDelete(row)"
            >
              <TrashIcon class="w-4 h-4" />
            </button>
          </div>
        </template>
      </BaseTable>
    </div>

    <!-- ── Modal Tambah/Edit ── -->
    <BaseModal v-model="showForm" :title="editId ? 'Edit Data Siswa' : 'Tambah Siswa Baru'" max-width="500px">
      <form @submit.prevent="submitForm" class="space-y-4">
        <BaseInput
          v-model="form.nis"
          label="NIS" id="nis"
          placeholder="Nomor Induk Siswa"
          :disabled="!!editId"
          :error="formErrors.nis"
          required
        />
        <BaseInput
          v-model="form.name"
          label="Nama Lengkap" id="name"
          placeholder="Nama siswa"
          :error="formErrors.name"
          required
        />
        <BaseSelect
          v-model="form.class_id"
          label="Kelas" id="class_id"
          :options="classOptions"
          placeholder="Pilih kelas"
          :error="formErrors.class_id"
          required
        />
        <BaseSelect
          v-model="form.status"
          label="Status" id="status"
          :options="[{ value: 'aktif', label: 'Aktif' }, { value: 'non_aktif', label: 'Non-Aktif' }]"
          placeholder="Pilih status"
        />
      </form>
      <template #footer>
        <BaseButton variant="secondary" @click="showForm = false">Batal</BaseButton>
        <BaseButton variant="primary" :loading="submitting" @click="submitForm">
          {{ editId ? 'Simpan Perubahan' : 'Tambah Siswa' }}
        </BaseButton>
      </template>
    </BaseModal>

    <!-- ── Modal Import Excel ── -->
    <BaseModal v-model="showImport" title="Import Data Siswa (Excel)" max-width="480px">
      <div class="space-y-4">
        <div class="p-4 bg-blue-50 rounded-xl text-sm text-blue-700 border border-blue-200">
          <p class="font-semibold mb-1">Format Excel yang dibutuhkan:</p>
          <p>Kolom: <code class="bg-blue-100 px-1 rounded">nis | name | class_name | status</code></p>
        </div>
        <div
          @drop.prevent="handleDrop" @dragover.prevent
          class="border-2 border-dashed border-gray-200 rounded-xl p-8 text-center hover:border-blue-300 transition-colors cursor-pointer"
          @click="$refs.fileInput.click()"
        >
          <ArrowUpTrayIcon class="w-8 h-8 text-gray-300 mx-auto mb-2" />
          <p class="text-sm text-gray-500">Klik atau drop file Excel di sini</p>
          <p class="text-xs text-gray-400 mt-1">.xlsx atau .xls</p>
          <input ref="fileInput" type="file" accept=".xlsx,.xls" class="hidden" @change="handleFileImport" />
        </div>
        <div v-if="importRows.length" class="text-sm text-gray-600">
          <p class="font-medium">{{ importRows.length }} baris ditemukan. Siap diimport.</p>
        </div>
      </div>
      <template #footer>
        <BaseButton variant="secondary" @click="showImport = false">Tutup</BaseButton>
        <BaseButton variant="primary" :loading="importing" :disabled="!importRows.length" @click="runImport">
          Import {{ importRows.length }} Siswa
        </BaseButton>
      </template>
    </BaseModal>

    <!-- ── Confirm Delete ── -->
    <ConfirmDialog
      ref="confirmRef"
      :message="`Siswa '${deleteTarget?.name}' akan dihapus permanen.`"
      @confirm="doDelete"
    />

    <!-- ── Print Area (hidden) ── -->
    <div id="print-area" class="hidden">
      <StudentCard v-if="printStudent" :student="printStudent" />
    </div>
  </DashboardLayout>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import * as XLSX from 'xlsx'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import BaseTable from '@/components/BaseTable.vue'
import BaseModal from '@/components/BaseModal.vue'
import BaseButton from '@/components/BaseButton.vue'
import BaseInput from '@/components/BaseInput.vue'
import BaseSelect from '@/components/BaseSelect.vue'
import BaseBadge from '@/components/BaseBadge.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import StudentCard from '@/components/StudentCard.vue'
import { studentsApi } from '@/api/studentsApi'
import { classesApi } from '@/api/classesApi'
import { useUiStore } from '@/stores/uiStore'
import {
  PlusIcon, MagnifyingGlassIcon, PencilIcon, TrashIcon,
  QrCodeIcon, PrinterIcon, ArrowUpTrayIcon
} from '@heroicons/vue/24/outline'

const uiStore = useUiStore()

// ── State ──
const loading   = ref(false)
const students  = ref([])
const classes   = ref([])
const pagination = ref(null)
const currentPage = ref(1)

const search       = ref('')
const filterClass  = ref('')
const filterStatus = ref('')

const showForm   = ref(false)
const showImport = ref(false)
const submitting = ref(false)
const importing  = ref(false)
const editId     = ref(null)

const form = reactive({ nis: '', name: '', class_id: '', status: 'aktif' })
const formErrors = reactive({ nis: '', name: '', class_id: '' })

const deleteTarget = ref(null)
const confirmRef   = ref(null)
const printStudent = ref(null)

const importRows = ref([])
const fileInput  = ref(null)

// ── Computed ──
const columns = [
  { key: 'nis',        label: 'NIS',      width: '120px' },
  { key: 'name',       label: 'Nama Lengkap' },
  { key: 'class_name', label: 'Kelas',    width: '120px' },
  { key: 'status',     label: 'Status',   width: '90px' },
  { key: 'qr_code',    label: 'QR Code',  width: '100px' },
]

const classOptions = computed(() =>
  classes.value.map(c => ({ value: String(c.id), label: c.name }))
)

const filteredStudents = computed(() => {
  let list = students.value
  if (search.value.trim()) {
    const q = search.value.toLowerCase()
    list = list.filter(s =>
      (s.name && s.name.toLowerCase().includes(q)) || (s.nis && s.nis.includes(q))
    )
  }
  if (filterStatus.value) {
    list = list.filter(s => s.status === filterStatus.value)
  }
  return list
})

// ── Methods ──
async function fetchStudents() {
  loading.value = true
  try {
    const params = { page: currentPage.value, limit: 20 }
    if (filterClass.value) params.class_id = filterClass.value
    const res = await studentsApi.list(params)
    const data = res.data.data
    students.value  = data.students || []
    pagination.value = data.pagination
  } catch {
    uiStore.error('Gagal memuat data siswa.')
  } finally {
    loading.value = false
  }
}

async function fetchClasses() {
  try {
    const res = await classesApi.listClasses()
    classes.value = res.data.data?.classes || []
  } catch { /* silent */ }
}

let debTimer = null
function debouncedFetch() {
  clearTimeout(debTimer)
  debTimer = setTimeout(fetchStudents, 350)
}

function openCreate() {
  editId.value = null
  Object.assign(form, { nis: '', name: '', class_id: '', status: 'aktif' })
  Object.assign(formErrors, { nis: '', name: '', class_id: '' })
  showForm.value = true
}

function openEdit(row) {
  editId.value = row.id
  Object.assign(form, {
    nis: row.nis, name: row.name,
    class_id: String(row.class_id), status: row.status
  })
  Object.assign(formErrors, { nis: '', name: '', class_id: '' })
  showForm.value = true
}

function validateForm() {
  formErrors.nis      = form.nis.trim()      ? '' : 'NIS wajib diisi'
  formErrors.name     = form.name.trim()     ? '' : 'Nama wajib diisi'
  formErrors.class_id = form.class_id        ? '' : 'Kelas wajib dipilih'
  return !formErrors.nis && !formErrors.name && !formErrors.class_id
}

async function submitForm() {
  if (!validateForm()) return
  submitting.value = true
  try {
    const payload = { ...form, class_id: Number(form.class_id) }
    if (editId.value) {
      await studentsApi.update(editId.value, { name: payload.name, class_id: payload.class_id, status: payload.status })
      uiStore.success('Data siswa berhasil diperbarui.')
    } else {
      await studentsApi.create(payload)
      uiStore.success('Siswa baru berhasil ditambahkan.')
    }
    showForm.value = false
    fetchStudents()
  } catch (err) {
    uiStore.error(err.response?.data?.message || 'Terjadi kesalahan.')
  } finally {
    submitting.value = false
  }
}

async function generateQr(row) {
  try {
    await studentsApi.regenerateQr(row.id)
    uiStore.success(`QR Code untuk ${row.name} berhasil di-generate ulang.`)
    fetchStudents()
  } catch (err) {
    uiStore.error(err.response?.data?.message || 'Gagal generate QR.')
  }
}

function confirmDelete(row) {
  deleteTarget.value = row
  confirmRef.value.open()
}

async function doDelete() {
  try {
    await studentsApi.delete(deleteTarget.value.id)
    uiStore.success(`Siswa ${deleteTarget.value.name} berhasil dihapus.`)
    fetchStudents()
  } catch (err) {
    uiStore.error(err.response?.data?.message || 'Gagal menghapus siswa.')
  } finally {
    confirmRef.value.close()
  }
}

function printCard(row) {
  printStudent.value = row
  const el = document.getElementById('print-area')
  el.classList.remove('hidden')
  setTimeout(() => { window.print(); el.classList.add('hidden') }, 200)
}

// ── Import Excel ──
function handleDrop(e) {
  const file = e.dataTransfer.files[0]
  if (file) parseExcel(file)
}

function handleFileImport(e) {
  const file = e.target.files[0]
  if (file) parseExcel(file)
}

function parseExcel(file) {
  const reader = new FileReader()
  reader.onload = (e) => {
    const wb = XLSX.read(e.target.result, { type: 'binary' })
    const ws = wb.Sheets[wb.SheetNames[0]]
    const rows = XLSX.utils.sheet_to_json(ws)
    importRows.value = rows
  }
  reader.readAsBinaryString(file)
}

async function runImport() {
  importing.value = true
  let success = 0, failed = 0
  for (const row of importRows.value) {
    try {
      const classObj = classes.value.find(c => c.name === row.class_name)
      if (!classObj) { failed++; continue }
      await studentsApi.create({
        nis:      String(row.nis),
        name:     row.name,
        class_id: classObj.id,
        status:   row.status || 'aktif'
      })
      success++
    } catch { failed++ }
  }
  importing.value = false
  showImport.value = false
  importRows.value = []
  uiStore.success(`Import selesai: ${success} berhasil, ${failed} gagal.`)
  fetchStudents()
}

onMounted(() => {
  fetchStudents()
  fetchClasses()
})
</script>
