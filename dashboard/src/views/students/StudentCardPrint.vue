<template>
  <!-- Halaman khusus cetak — hanya tampil saat print -->
  <div class="print-page">
    <!-- Screen preview (sebelum cetak) -->
    <div class="no-print bg-gray-100 min-h-screen p-8">
      <div class="max-w-4xl mx-auto">
        <div class="flex items-center justify-between mb-6">
          <div>
            <h1 class="text-2xl font-bold text-gray-900">Preview Kartu Pelajar</h1>
            <p class="text-gray-500 text-sm mt-1">{{ students.length }} kartu siap dicetak</p>
          </div>
          <div class="flex gap-3">
            <RouterLink to="/students" class="btn-secondary btn">
              ← Kembali
            </RouterLink>
            <button @click="window.print()" class="btn-primary btn">
              <PrinterIcon class="w-4 h-4" /> Cetak Semua
            </button>
          </div>
        </div>

        <div v-if="!students.length" class="text-center py-16 text-gray-400">
          <p>Tidak ada siswa yang dipilih untuk dicetak.</p>
          <RouterLink to="/students" class="text-blue-600 hover:underline text-sm mt-2 inline-block">
            ← Kembali ke Data Siswa
          </RouterLink>
        </div>

        <div class="flex flex-wrap gap-6 justify-center">
          <div v-for="s in students" :key="s.id"
               class="bg-white rounded-2xl p-4 shadow-md flex flex-col items-center gap-3">
            <StudentCard :student="s" />
            <p class="text-xs text-gray-400">{{ s.name }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Print area: hanya kartu -->
    <div id="print-area" class="only-print">
      <div class="cards-grid">
        <StudentCard v-for="s in students" :key="s.id" :student="s" />
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import StudentCard from '@/components/StudentCard.vue'
import { studentsApi } from '@/api/studentsApi'
import { PrinterIcon } from '@heroicons/vue/24/outline'

const route    = useRoute()
const students = ref([])

onMounted(async () => {
  const ids = route.query.ids ? String(route.query.ids).split(',').map(Number) : []
  if (!ids.length) {
    // Ambil semua siswa jika tidak ada ID spesifik
    const res = await studentsApi.list({ limit: 100 })
    students.value = res.data?.data?.students || []
  } else {
    const results = await Promise.allSettled(ids.map(id => studentsApi.detail(id)))
    students.value = results.filter(r => r.status === 'fulfilled').map(r => r.value.data.data)
  }
})
</script>

<style scoped>
@media screen {
  .only-print {
    display: none;
  }
}

@media print {
  .no-print {
    display: none !important;
  }
  .only-print {
    display: block;
  }
  .cards-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 5mm;
    padding: 5mm;
  }
}
</style>
