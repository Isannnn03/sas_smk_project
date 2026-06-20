<template>
  <DashboardLayout>
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
      <div>
        <h2 class="text-xl font-bold text-gray-900">Kelas & Mata Pelajaran</h2>
        <p class="text-sm text-gray-500">Kelola data kelas dan mata pelajaran</p>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
      <!-- ── Kelas ── -->
      <div class="card p-0">
        <div class="flex items-center justify-between p-4 border-b border-gray-100">
          <h3 class="font-semibold text-gray-900">Data Kelas</h3>
          <BaseButton variant="primary" size="sm" @click="openCreateClass">
            <PlusIcon class="w-3.5 h-3.5" /> Tambah
          </BaseButton>
        </div>
        <BaseTable :columns="classColumns" :rows="classes" :loading="loadingClasses" empty-text="Belum ada kelas">
          <template #actions="{ row }">
            <div class="flex items-center justify-end gap-1">
              <button class="btn btn-sm btn-ghost text-amber-600 hover:bg-amber-50" @click="openEditClass(row)">
                <PencilIcon class="w-4 h-4" />
              </button>
              <button class="btn btn-sm btn-ghost text-red-600 hover:bg-red-50" @click="confirmDeleteClass(row)">
                <TrashIcon class="w-4 h-4" />
              </button>
            </div>
          </template>
        </BaseTable>
      </div>

      <!-- ── Mata Pelajaran ── -->
      <div class="card p-0">
        <div class="flex items-center justify-between p-4 border-b border-gray-100">
          <h3 class="font-semibold text-gray-900">Mata Pelajaran</h3>
          <BaseButton variant="primary" size="sm" @click="openCreateSubject">
            <PlusIcon class="w-3.5 h-3.5" /> Tambah
          </BaseButton>
        </div>
        <BaseTable :columns="subjectColumns" :rows="subjects" :loading="loadingSubjects" empty-text="Belum ada mapel">
          <template #actions="{ row }">
            <div class="flex items-center justify-end gap-1">
              <button class="btn btn-sm btn-ghost text-amber-600 hover:bg-amber-50" @click="openEditSubject(row)">
                <PencilIcon class="w-4 h-4" />
              </button>
              <button class="btn btn-sm btn-ghost text-red-600 hover:bg-red-50" @click="confirmDeleteSubject(row)">
                <TrashIcon class="w-4 h-4" />
              </button>
            </div>
          </template>
        </BaseTable>
      </div>
    </div>

    <!-- Modal Kelas -->
    <BaseModal v-model="showClassForm" :title="classEditId ? 'Edit Kelas' : 'Tambah Kelas'" max-width="420px">
      <form @submit.prevent="submitClass" class="space-y-4">
        <BaseInput v-model="classForm.name" label="Nama Kelas" id="class-name" placeholder="Contoh: X RPL 1" :error="classErrors.name" required />
        <BaseInput v-model="classForm.grade" label="Tingkat" id="class-grade" placeholder="Contoh: X, XI, XII" />
        <BaseInput v-model="classForm.major" label="Jurusan" id="class-major" placeholder="Contoh: RPL, TKJ" />
      </form>
      <template #footer>
        <BaseButton variant="secondary" @click="showClassForm = false">Batal</BaseButton>
        <BaseButton variant="primary" :loading="submittingClass" @click="submitClass">Simpan</BaseButton>
      </template>
    </BaseModal>

    <!-- Modal Mapel -->
    <BaseModal v-model="showSubjectForm" :title="subjectEditId ? 'Edit Mata Pelajaran' : 'Tambah Mata Pelajaran'" max-width="420px">
      <form @submit.prevent="submitSubject" class="space-y-4">
        <BaseInput v-model="subjectForm.name" label="Nama Mata Pelajaran" id="subject-name" placeholder="Contoh: Pemrograman Web" :error="subjectErrors.name" required />
        <BaseInput v-model="subjectForm.code" label="Kode Mapel" id="subject-code" placeholder="Contoh: PWE" />
      </form>
      <template #footer>
        <BaseButton variant="secondary" @click="showSubjectForm = false">Batal</BaseButton>
        <BaseButton variant="primary" :loading="submittingSubject" @click="submitSubject">Simpan</BaseButton>
      </template>
    </BaseModal>

    <ConfirmDialog ref="confirmClassRef" :message="`Kelas '${classDeleteTarget?.name}' akan dihapus.`" @confirm="doDeleteClass" />
    <ConfirmDialog ref="confirmSubjectRef" :message="`Mapel '${subjectDeleteTarget?.name}' akan dihapus.`" @confirm="doDeleteSubject" />
  </DashboardLayout>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import BaseTable from '@/components/BaseTable.vue'
import BaseModal from '@/components/BaseModal.vue'
import BaseButton from '@/components/BaseButton.vue'
import BaseInput from '@/components/BaseInput.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import { classesApi } from '@/api/classesApi'
import { useUiStore } from '@/stores/uiStore'
import { PlusIcon, PencilIcon, TrashIcon } from '@heroicons/vue/24/outline'

const uiStore = useUiStore()

// State Kelas
const classes = ref([])
const loadingClasses = ref(false)
const showClassForm = ref(false)
const submittingClass = ref(false)
const classEditId = ref(null)
const classDeleteTarget = ref(null)
const confirmClassRef = ref(null)
const classForm = reactive({ name: '', grade: '', major: '' })
const classErrors = reactive({ name: '' })

// State Mapel
const subjects = ref([])
const loadingSubjects = ref(false)
const showSubjectForm = ref(false)
const submittingSubject = ref(false)
const subjectEditId = ref(null)
const subjectDeleteTarget = ref(null)
const confirmSubjectRef = ref(null)
const subjectForm = reactive({ name: '', code: '' })
const subjectErrors = reactive({ name: '' })

const classColumns = [
  { key: 'name', label: 'Nama Kelas' },
  { key: 'grade', label: 'Tingkat', width: '80px' },
  { key: 'major', label: 'Jurusan' },
]

const subjectColumns = [
  { key: 'code', label: 'Kode', width: '80px' },
  { key: 'name', label: 'Nama Mapel' },
]

async function fetchClasses() {
  loadingClasses.value = true
  try {
    const res = await classesApi.listClasses()
    classes.value = res.data?.data?.classes || []
  } catch { uiStore.error('Gagal memuat data kelas.') }
  finally { loadingClasses.value = false }
}

async function fetchSubjects() {
  loadingSubjects.value = true
  try {
    const res = await classesApi.listSubjects()
    subjects.value = res.data?.data?.subjects || []
  } catch { uiStore.error('Gagal memuat data mapel.') }
  finally { loadingSubjects.value = false }
}

function openCreateClass() {
  classEditId.value = null
  Object.assign(classForm, { name: '', grade: '', major: '' })
  classErrors.name = ''
  showClassForm.value = true
}
function openEditClass(row) {
  classEditId.value = row.id
  Object.assign(classForm, { name: row.name, grade: row.grade || '', major: row.major || '' })
  showClassForm.value = true
}
async function submitClass() {
  classErrors.name = classForm.name.trim() ? '' : 'Nama kelas wajib diisi'
  if (classErrors.name) return
  submittingClass.value = true
  try {
    if (classEditId.value) {
      await classesApi.updateClass(classEditId.value, classForm)
      uiStore.success('Kelas berhasil diperbarui.')
    } else {
      await classesApi.createClass(classForm)
      uiStore.success('Kelas baru berhasil ditambahkan.')
    }
    showClassForm.value = false; fetchClasses()
  } catch (err) { uiStore.error(err.response?.data?.message || 'Gagal menyimpan kelas.')
  } finally { submittingClass.value = false }
}
function confirmDeleteClass(row) { classDeleteTarget.value = row; confirmClassRef.value.open() }
async function doDeleteClass() {
  try { await classesApi.deleteClass(classDeleteTarget.value.id); uiStore.success('Kelas dihapus.'); fetchClasses()
  } catch (err) { uiStore.error(err.response?.data?.message || 'Gagal hapus kelas.')
  } finally { confirmClassRef.value.close() }
}

function openCreateSubject() {
  subjectEditId.value = null
  Object.assign(subjectForm, { name: '', code: '' })
  subjectErrors.name = ''
  showSubjectForm.value = true
}
function openEditSubject(row) {
  subjectEditId.value = row.id
  Object.assign(subjectForm, { name: row.name, code: row.code || '' })
  showSubjectForm.value = true
}
async function submitSubject() {
  subjectErrors.name = subjectForm.name.trim() ? '' : 'Nama mapel wajib diisi'
  if (subjectErrors.name) return
  submittingSubject.value = true
  try {
    if (subjectEditId.value) {
      await classesApi.updateSubject(subjectEditId.value, subjectForm)
      uiStore.success('Mapel berhasil diperbarui.')
    } else {
      await classesApi.createSubject(subjectForm)
      uiStore.success('Mapel baru berhasil ditambahkan.')
    }
    showSubjectForm.value = false; fetchSubjects()
  } catch (err) { uiStore.error(err.response?.data?.message || 'Gagal menyimpan mapel.')
  } finally { submittingSubject.value = false }
}
function confirmDeleteSubject(row) { subjectDeleteTarget.value = row; confirmSubjectRef.value.open() }
async function doDeleteSubject() {
  try { await classesApi.deleteSubject(subjectDeleteTarget.value.id); uiStore.success('Mapel dihapus.'); fetchSubjects()
  } catch (err) { uiStore.error(err.response?.data?.message || 'Gagal hapus mapel.')
  } finally { confirmSubjectRef.value.close() }
}

onMounted(() => { fetchClasses(); fetchSubjects() })
</script>
