<template>
  <div class="table-wrapper">
    <!-- Search + actions bar slot -->
    <div v-if="$slots.toolbar" class="px-4 py-3 border-b border-gray-100 bg-white">
      <slot name="toolbar" />
    </div>

    <table class="data-table">
      <thead>
        <tr>
          <th v-for="col in columns" :key="col.key" :style="col.width ? `width:${col.width}` : ''">
            {{ col.label }}
          </th>
          <th v-if="$slots.actions" class="text-right">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <!-- Loading skeleton -->
        <tr v-if="loading" v-for="n in 5" :key="`sk-${n}`">
          <td v-for="col in columns" :key="col.key">
            <div class="h-4 bg-gray-100 rounded animate-pulse w-3/4"></div>
          </td>
          <td v-if="$slots.actions"><div class="h-4 bg-gray-100 rounded animate-pulse w-20 ml-auto"></div></td>
        </tr>

        <!-- Empty state -->
        <tr v-else-if="!rows.length">
          <td :colspan="columns.length + ($slots.actions ? 1 : 0)" class="text-center py-16">
            <div class="flex flex-col items-center gap-2 text-gray-400">
              <FolderOpenIcon class="w-10 h-10" />
              <span class="text-sm">{{ emptyText }}</span>
            </div>
          </td>
        </tr>

        <!-- Data rows -->
        <template v-else>
          <tr v-for="(row, idx) in rows" :key="row.id ?? idx">
            <td v-for="col in columns" :key="col.key">
              <slot :name="`cell-${col.key}`" :row="row" :value="row[col.key]">
                {{ row[col.key] ?? '—' }}
              </slot>
            </td>
            <td v-if="$slots.actions" class="text-right">
              <slot name="actions" :row="row" />
            </td>
          </tr>
        </template>
      </tbody>
    </table>

    <!-- Pagination -->
    <div v-if="pagination && pagination.total_pages > 1"
         class="flex items-center justify-between px-4 py-3 border-t border-gray-100 bg-white text-sm">
      <span class="text-gray-500">
        Halaman {{ pagination.current_page }} dari {{ pagination.total_pages }}
        ({{ pagination.total_items }} data)
      </span>
      <div class="flex gap-1">
        <button
          class="btn btn-ghost btn-sm"
          :disabled="pagination.current_page <= 1"
          @click="$emit('page-change', pagination.current_page - 1)"
        >
          <ChevronLeftIcon class="w-4 h-4" />
        </button>
        <button
          class="btn btn-ghost btn-sm"
          :disabled="pagination.current_page >= pagination.total_pages"
          @click="$emit('page-change', pagination.current_page + 1)"
        >
          <ChevronRightIcon class="w-4 h-4" />
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { FolderOpenIcon, ChevronLeftIcon, ChevronRightIcon } from '@heroicons/vue/24/outline'

defineProps({
  columns:    { type: Array, required: true },   // [{ key, label, width? }]
  rows:       { type: Array, default: () => [] },
  loading:    Boolean,
  emptyText:  { type: String, default: 'Belum ada data' },
  pagination: Object,                             // { total_items, total_pages, current_page, limit }
})

defineEmits(['page-change'])
</script>
