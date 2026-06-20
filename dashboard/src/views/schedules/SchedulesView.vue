<template>
  <DashboardLayout>
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
      <div>
        <h2 class="text-xl font-bold text-gray-900">Jadwal Pelajaran</h2>
        <p class="text-sm text-gray-500">Kelola mapping guru, mata pelajaran, kelas, dan jam mengajar</p>
      </div>
      <BaseButton variant="primary" @click="openCreate">
        <PlusIcon class="w-4 h-4" /> Tambah Jadwal
      </BaseButton>
    </div>

    <!-- Filter by day -->
    <div class="flex gap-2 mb-4 overflow-x-auto pb-1">
      <button
        v-for="day in ['', ...days]" :key="day"
        @click="filterDay = day; fetchSchedules()"
        :class="[
          'btn btn-sm flex-shrink-0',
          filterDay === day ? 'btn-primary' : 'btn-secondary'
        ]"
      >{{ day || 'Semua Hari' }}</button>
    </div>

    <div class="card p-0">
      <BaseTable :columns="columns" :rows="schedules" :loading="loading"
        :pagination="pagination" empty-text="Belum ada jadwal"
        @page-change="(p) => { currentPage = p; fetchSchedules() }">

        <template #cell-day="{ value }">
          <BaseBadge :color="dayColor(value)" :label="value" />
        </template>

        <template #cell-time="{ row }">
          <span class="font-mono text-sm">{{ row.start_time }} – {{ row.end_time }}</span>
        </template>

        <template #actions="{ row }">
          <div class="flex items-center justify-end gap-1">
            <button class="btn btn-sm btn-ghost text-amber-600 hover:bg-amber-50" @click="openEdit(row)">
              <PencilIcon class="w-4 h-4" />
            </button>
            <button class="btn btn-sm btn-ghost text-red-600 hover:bg-red-50" @click="confirmDelete(row)">
              <TrashIcon class="w-4 h-4" />
            </button>
          </div>
        </template>
      </BaseTable>
    </div>

    <!-- Modal Form -->
    <BaseModal v-model="showForm" :title="editId ? 'Edit Jadwal' : 'Tambah Jadwal Baru'" max-width="520px">
      <form @submit.prevent="submitForm" class="space-y-4">
        <BaseSelect v-model="form.class_id" label="Kelas" id="sched-class"
          :options="classOptions" placeholder="Pilih kelas" :error="formErrors.class_id" required />
        <BaseSelect v-model="form.teacher_id" label="Guru" id="sched-teacher"
          :options="teacherOptions" placeholder="Pilih guru" :error="formErrors.teacher_id" required />
        <BaseSelect v-model="form.subject_id" label="Mata Pelajaran" id="sched-subject"
          :options="subjectOptions" placeholder="Pilih mapel" :error="formErrors.subject_id" required />
        <BaseSelect v-model="form.day" label="Hari" id="sched-day"
          :options="dayOptions" placeholder="Pilih hari" :error="formErrors.day" required />
        <div class="grid grid-cols-2 gap-4">
          <BaseInput v-model="form.start_time" label="Jam Mulai" id="start-time" type="time" :error="formErrors.start_time" required />
          <BaseInput v-model="form.end_time" label="Jam Selesai" id="end-time" type="time" :error="formErrors.end_time" required />
        </div>
      </form>
      <template #footer>
        <BaseButton variant="secondary" @click="showForm = false">Batal</BaseButton>
        <BaseButton variant="primary" :loading="submitting" @click="submitForm">
          {{ editId ? 'Simpan Perubahan' : 'Buat Jadwal' }}
        </BaseButton>
      </template>
    </BaseModal>

    <ConfirmDialog ref="confirmRef" :message="`Jadwal ini akan dihapus beserta semua sesi absensinya.`" @confirm="doDelete" />
  </DashboardLayout>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import BaseTable from '@/components/BaseTable.vue'
import BaseModal from '@/components/BaseModal.vue'
import BaseButton from '@/components/BaseButton.vue'
import BaseInput from '@/components/BaseInput.vue'
import BaseSelect from '@/components/BaseSelect.vue'
import BaseBadge from '@/components/BaseBadge.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import { schedulesApi } from '@/api/schedulesApi'
import { classesApi } from '@/api/classesApi'
import { teachersApi } from '@/api/teachersApi'
import { useUiStore } from '@/stores/uiStore'
import { PlusIcon, PencilIcon, TrashIcon } from '@heroicons/vue/24/outline'

const uiStore = useUiStore()

const days = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu']
const dayOptions = days.map(d => ({ value: d, label: d }))

const loading    = ref(false)
const schedules  = ref([])
const classes    = ref([])
const teachers   = ref([])
const subjects   = ref([])
const pagination = ref(null)
const currentPage = ref(1)
const filterDay  = ref('')

const showForm   = ref(false)
const submitting = ref(false)
const editId     = ref(null)
const deleteTarget = ref(null)
const confirmRef = ref(null)

const form = reactive({ class_id: '', teacher_id: '', subject_id: '', day: '', start_time: '', end_time: '' })
const formErrors = reactive({ class_id: '', teacher_id: '', subject_id: '', day: '', start_time: '', end_time: '' })

const columns = [
  { key: 'day',          label: 'Hari',       width: '90px' },
  { key: 'time',         label: 'Waktu',      width: '130px' },
  { key: 'class_name',   label: 'Kelas',      width: '100px' },
  { key: 'subject_name', label: 'Mata Pelajaran' },
  { key: 'teacher_name', label: 'Guru' },
]

const classOptions   = computed(() => classes.value.map(c => ({ value: String(c.id), label: c.name })))
const teacherOptions = computed(() => teachers.value.map(t => ({ value: String(t.id), label: t.name })))
const subjectOptions = computed(() => subjects.value.map(s => ({ value: String(s.id), label: s.name })))

function dayColor(day) {
  const map = { Senin: 'blue', Selasa: 'green', Rabu: 'purple', Kamis: 'yellow', Jumat: 'red', Sabtu: 'navy' }
  return map[day] || 'gray'
}

async function fetchSchedules() {
  loading.value = true
  try {
    const params = { page: currentPage.value, limit: 20 }
    if (filterDay.value) params.day = filterDay.value
    const res = await schedulesApi.list(params)
    schedules.value  = res.data.data?.schedules || []
    pagination.value = res.data.data?.pagination
  } catch { uiStore.error('Gagal memuat jadwal.') }
  finally { loading.value = false }
}

async function fetchDropdowns() {
  const [cls, tch, sub] = await Promise.allSettled([
    classesApi.listClasses(),
    teachersApi.list(),
    classesApi.listSubjects(),
  ])
  if (cls.status === 'fulfilled') classes.value  = cls.value.data?.data?.classes  || []
  if (tch.status === 'fulfilled') teachers.value = tch.value.data?.data?.teachers || []
  if (sub.status === 'fulfilled') subjects.value = sub.value.data?.data?.subjects || []
}

function openCreate() {
  editId.value = null
  Object.assign(form, { class_id: '', teacher_id: '', subject_id: '', day: '', start_time: '', end_time: '' })
  Object.keys(formErrors).forEach(k => formErrors[k] = '')
  showForm.value = true
}
function openEdit(row) {
  editId.value = row.id
  Object.assign(form, {
    class_id: String(row.class_id), teacher_id: String(row.teacher_id),
    subject_id: String(row.subject_id), day: row.day,
    start_time: row.start_time?.substring(0, 5) || '',
    end_time:   row.end_time?.substring(0, 5)   || '',
  })
  showForm.value = true
}

function validateForm() {
  const required = ['class_id', 'teacher_id', 'subject_id', 'day', 'start_time', 'end_time']
  let valid = true
  required.forEach(k => {
    formErrors[k] = form[k] ? '' : 'Field ini wajib diisi'
    if (!form[k]) valid = false
  })
  return valid
}

async function submitForm() {
  if (!validateForm()) return
  submitting.value = true
  try {
    const payload = {
      class_id: Number(form.class_id), teacher_id: Number(form.teacher_id),
      subject_id: Number(form.subject_id), day: form.day,
      start_time: form.start_time, end_time: form.end_time
    }
    if (editId.value) {
      await schedulesApi.update(editId.value, payload)
      uiStore.success('Jadwal berhasil diperbarui.')
    } else {
      await schedulesApi.create(payload)
      uiStore.success('Jadwal baru berhasil dibuat.')
    }
    showForm.value = false; fetchSchedules()
  } catch (err) { uiStore.error(err.response?.data?.message || 'Terjadi kesalahan.') }
  finally { submitting.value = false }
}

function confirmDelete(row) { deleteTarget.value = row; confirmRef.value.open() }
async function doDelete() {
  try { await schedulesApi.delete(deleteTarget.value.id); uiStore.success('Jadwal dihapus.'); fetchSchedules()
  } catch (err) { uiStore.error(err.response?.data?.message || 'Gagal hapus jadwal.')
  } finally { confirmRef.value.close() }
}

onMounted(() => { fetchSchedules(); fetchDropdowns() })
</script>
