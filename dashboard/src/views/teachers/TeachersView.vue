<template>
  <DashboardLayout>
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
      <div>
        <h2 class="text-xl font-bold text-gray-900">Data Guru</h2>
        <p class="text-sm text-gray-500">Kelola data pengajar dan informasi guru</p>
      </div>
      <BaseButton variant="primary" @click="openCreate">
        <PlusIcon class="w-4 h-4" /> Tambah Guru
      </BaseButton>
    </div>

    <div class="card p-0">
      <!-- Search bar -->
      <div class="flex items-center gap-3 p-4 border-b border-gray-100">
        <div class="relative flex-1 max-w-sm">
          <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
          <input v-model="search" type="text" placeholder="Cari nama / NIP..." class="form-input pl-9" />
        </div>
      </div>

      <BaseTable :columns="columns" :rows="filteredTeachers" :loading="loading" empty-text="Belum ada data guru">
        <template #cell-password>
          <span class="text-gray-400">***</span>
        </template>
        <template #cell-status="{ value }">
          <BaseBadge :color="value === 'aktif' ? 'green' : 'gray'" :label="value || 'aktif'" />
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
    <BaseModal v-model="showForm" :title="editId ? 'Edit Data Guru' : 'Tambah Guru Baru'" max-width="480px">
      <form @submit.prevent="submitForm" class="space-y-4">
        <BaseInput v-model="form.nip" label="NIP" id="nip" placeholder="Nomor Induk Pegawai" :error="formErrors.nip" required />
        <BaseInput v-model="form.password" type="password" :label="editId ? 'Password Baru (Opsional)' : 'Password Login'" id="teacher-password" :placeholder="editId ? 'Kosongkan jika tidak diubah' : 'Buat password login mobile'" :error="formErrors.password" :required="!editId" />
        <BaseInput v-model="form.name" label="Nama Lengkap" id="teacher-name" placeholder="Nama guru" :error="formErrors.name" required />
        <BaseInput v-model="form.email" label="Email" id="teacher-email" type="email" placeholder="email@sekolah.id" />
        <BaseInput v-model="form.phone" label="No. Telepon" id="teacher-phone" placeholder="08xx-xxxx-xxxx" />
        <BaseSelect
          v-model="form.status"
          label="Status" id="teacher-status"
          :options="[{ value: 'aktif', label: 'Aktif' }, { value: 'non_aktif', label: 'Non-Aktif' }]"
        />
      </form>
      <template #footer>
        <BaseButton variant="secondary" @click="showForm = false">Batal</BaseButton>
        <BaseButton variant="primary" :loading="submitting" @click="submitForm">
          {{ editId ? 'Simpan' : 'Tambah Guru' }}
        </BaseButton>
      </template>
    </BaseModal>

    <ConfirmDialog ref="confirmRef" :message="`Guru '${deleteTarget?.name}' akan dihapus.`" @confirm="doDelete" />
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
import { teachersApi } from '@/api/teachersApi'
import { useUiStore } from '@/stores/uiStore'
import { PlusIcon, PencilIcon, TrashIcon, MagnifyingGlassIcon } from '@heroicons/vue/24/outline'

const uiStore = useUiStore()
const loading   = ref(false)
const teachers  = ref([])
const search    = ref('')
const showForm  = ref(false)
const submitting = ref(false)
const editId    = ref(null)
const deleteTarget = ref(null)
const confirmRef   = ref(null)

const form = reactive({ nip: '', password: '', name: '', email: '', phone: '', status: 'aktif' })
const formErrors = reactive({ nip: '', password: '', name: '' })

const columns = [
  { key: 'nip',   label: 'NIP',          width: '140px' },
  { key: 'name',  label: 'Nama Guru' },
  { key: 'password', label: 'Password' },
  { key: 'email', label: 'Email' },
  { key: 'phone', label: 'Telepon',      width: '140px' },
  { key: 'status',label: 'Status',       width: '90px' },
]

const filteredTeachers = computed(() => {
  if (!search.value.trim()) return teachers.value
  const q = search.value.toLowerCase()
  return teachers.value.filter(t =>
    (t.name && t.name.toLowerCase().includes(q)) || (t.nip && t.nip.includes(q))
  )
})

async function fetchTeachers() {
  loading.value = true
  try {
    const res = await teachersApi.list()
    teachers.value = res.data?.data?.teachers || []
  } catch {
    uiStore.error('Gagal memuat data guru.')
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editId.value = null
  Object.assign(form, { nip: '', password: '', name: '', email: '', phone: '', status: 'aktif' })
  Object.assign(formErrors, { nip: '', password: '', name: '' })
  showForm.value = true
}

function openEdit(row) {
  editId.value = row.id
  Object.assign(form, { nip: row.nip || '', password: '', name: row.name, email: row.email || '', phone: row.phone || '', status: row.status || 'aktif' })
  showForm.value = true
}

function validateForm() {
  formErrors.nip  = form.nip.trim()  ? '' : 'NIP wajib diisi'
  if (!editId.value) {
    formErrors.password = form.password.trim() ? '' : 'Password wajib diisi'
  } else {
    formErrors.password = ''
  }
  formErrors.name = form.name.trim() ? '' : 'Nama wajib diisi'
  return !formErrors.nip && !formErrors.password && !formErrors.name
}

async function submitForm() {
  if (!validateForm()) return
  submitting.value = true
  try {
    if (editId.value) {
      await teachersApi.update(editId.value, form)
      uiStore.success('Data guru berhasil diperbarui.')
    } else {
      await teachersApi.create(form)
      uiStore.success('Guru baru berhasil ditambahkan.')
    }
    showForm.value = false
    fetchTeachers()
  } catch (err) {
    uiStore.error(err.response?.data?.message || 'Terjadi kesalahan.')
  } finally {
    submitting.value = false
  }
}

function confirmDelete(row) {
  deleteTarget.value = row
  confirmRef.value.open()
}

async function doDelete() {
  try {
    await teachersApi.delete(deleteTarget.value.id)
    uiStore.success('Guru berhasil dihapus.')
    fetchTeachers()
  } catch (err) {
    uiStore.error(err.response?.data?.message || 'Gagal menghapus guru.')
  } finally {
    confirmRef.value.close()
  }
}

onMounted(fetchTeachers)
</script>
