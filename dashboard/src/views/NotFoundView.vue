<template>
  <div class="min-h-screen flex flex-col items-center justify-center bg-gray-50">
    <div class="text-center">
      <p class="text-8xl font-black text-gray-200">404</p>
      <h1 class="text-xl font-bold text-gray-700 mt-4">Halaman Tidak Ditemukan</h1>
      <p class="text-gray-400 text-sm mt-2">Halaman yang kamu cari tidak ada.</p>
      <RouterLink to="/dashboard" class="btn-primary btn mt-6 inline-flex">
        Kembali ke Dashboard
      </RouterLink>
    </div>
  </div>
</template>

<script setup>
import { RouterLink } from 'vue-router'
</script>
