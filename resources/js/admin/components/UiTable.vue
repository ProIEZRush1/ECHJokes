<template>
  <div>
    <!-- Desktop table -->
    <div class="hidden md:block overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-white/10">
            <th
              v-for="col in columns"
              :key="col.key"
              :class="[
                'text-left px-4 py-3 text-[11px] uppercase tracking-wide text-gray-400 font-semibold whitespace-nowrap',
                col.align === 'right' && 'text-right',
                col.align === 'center' && 'text-center',
                col.sortable && 'cursor-pointer select-none hover:text-white transition',
              ]"
              :style="col.width ? { width: col.width } : null"
              @click="col.sortable && toggleSort(col.key)"
            >
              <span class="inline-flex items-center gap-1.5">
                {{ col.label }}
                <span v-if="col.sortable" class="text-gray-600 text-[10px]">
                  <span v-if="sortKey === col.key">{{ sortDir === 'asc' ? '▲' : '▼' }}</span>
                  <span v-else class="opacity-50">↕</span>
                </span>
              </span>
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td :colspan="columns.length" class="px-4 py-3">
              <div class="space-y-2">
                <UiSkeleton v-for="n in 5" :key="n" h="22px" />
              </div>
            </td>
          </tr>
          <template v-else>
            <tr
              v-for="(row, i) in rows"
              :key="rowKey ? row[rowKey] : i"
              :class="[
                'group border-b border-white/5 transition cursor-pointer hover:bg-white/[0.025] animate-[fade-in-up_0.3s_ease-out_both]',
                rowClass ? rowClass(row) : '',
              ]"
              :style="{ animationDelay: (i * 30) + 'ms' }"
              @click="$emit('row-click', row)"
            >
              <td
                v-for="col in columns"
                :key="col.key"
                :class="[
                  'px-4 py-3 align-middle',
                  col.align === 'right' && 'text-right',
                  col.align === 'center' && 'text-center',
                  col.mono && 'font-mono text-[13px]',
                ]"
              >
                <slot :name="`cell-${col.key}`" :row="row" :value="row[col.key]">{{ row[col.key] }}</slot>
              </td>
            </tr>
            <tr v-if="!rows.length">
              <td :colspan="columns.length" class="px-4 py-12">
                <slot name="empty">
                  <UiEmptyState :title="emptyTitle" :body="emptyBody" />
                </slot>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>

    <!-- Mobile cards -->
    <div class="md:hidden space-y-2">
      <template v-if="loading">
        <div v-for="n in 3" :key="n" class="surface-card p-4">
          <UiSkeleton class="mb-2" h="14px" w="60%" />
          <UiSkeleton h="12px" w="40%" />
        </div>
      </template>
      <template v-else-if="rows.length">
        <div
          v-for="(row, i) in rows"
          :key="rowKey ? row[rowKey] : i"
          class="surface-card p-4 lift cursor-pointer animate-[fade-in-up_0.3s_ease-out_both]"
          :style="{ animationDelay: (i * 30) + 'ms' }"
          @click="$emit('row-click', row)"
        >
          <div v-for="col in columns" :key="col.key" class="flex justify-between gap-3 py-1">
            <span class="text-xs text-gray-500 uppercase tracking-wide">{{ col.label }}</span>
            <span :class="['text-sm text-right', col.mono && 'font-mono text-[13px]']">
              <slot :name="`cell-${col.key}`" :row="row" :value="row[col.key]">{{ row[col.key] }}</slot>
            </span>
          </div>
        </div>
      </template>
      <UiEmptyState v-else :title="emptyTitle" :body="emptyBody" />
    </div>

    <!-- Pagination -->
    <div v-if="pagination && pagination.lastPage > 1" class="flex items-center justify-between pt-4 mt-2 border-t border-white/5 text-xs text-gray-400">
      <div>
        Página <span class="text-white font-semibold">{{ pagination.currentPage }}</span> de {{ pagination.lastPage }} ·
        <span class="text-gray-500">{{ pagination.total }} resultados</span>
      </div>
      <div class="flex gap-1.5">
        <UiButton size="sm" variant="ghost" :disabled="pagination.currentPage === 1" @click="$emit('page', pagination.currentPage - 1)">
          ← Anterior
        </UiButton>
        <UiButton size="sm" variant="ghost" :disabled="pagination.currentPage >= pagination.lastPage" @click="$emit('page', pagination.currentPage + 1)">
          Siguiente →
        </UiButton>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import UiSkeleton from './UiSkeleton.vue'
import UiEmptyState from './UiEmptyState.vue'
import UiButton from './UiButton.vue'

const props = defineProps({
  // columns: [{ key, label, sortable, align, width, mono }]
  columns:    { type: Array, required: true },
  rows:       { type: Array, default: () => [] },
  rowKey:     { type: String, default: 'id' },
  rowClass:   { type: Function, default: null },
  loading:    { type: Boolean, default: false },
  emptyTitle: { type: String, default: 'Sin resultados' },
  emptyBody:  { type: String, default: '' },
  pagination: { type: Object, default: null }, // { currentPage, lastPage, total }
  defaultSort:{ type: Object, default: null },  // { key, dir }
})
const emit = defineEmits(['row-click', 'sort', 'page'])

const sortKey = ref(props.defaultSort?.key || null)
const sortDir = ref(props.defaultSort?.dir || 'asc')

function toggleSort(key) {
  if (sortKey.value === key) {
    sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortKey.value = key
    sortDir.value = 'desc'
  }
  emit('sort', { key: sortKey.value, dir: sortDir.value })
}
</script>
