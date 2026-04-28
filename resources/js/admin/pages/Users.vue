<template>
  <div class="p-6 lg:p-8 space-y-5 max-w-[1400px]">
    <header>
      <div class="text-[11.5px] uppercase tracking-wider text-gray-500 font-semibold mb-1">Datos</div>
      <h1 class="text-[26px] font-bold tracking-tight">Users</h1>
      <p class="text-sm text-gray-400 mt-1.5">Buscar y gestionar cuentas.</p>
    </header>

    <UiCard :padded="false">
      <template #header>
        <h2 class="text-[14px] font-semibold text-white">{{ count }} usuario{{ count === 1 ? '' : 's' }}</h2>
      </template>
      <template #actions>
        <div class="relative w-72 max-w-[60vw]">
          <Search class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-500" />
          <input
            v-model="search"
            @input="debouncedFetch"
            placeholder="Buscar por nombre o email…"
            class="w-full bg-white/5 border border-white/10 rounded-lg pl-9 pr-3 py-1.5 text-sm text-white placeholder:text-gray-500 focus:outline-none focus:border-neon/50 transition"
          />
        </div>
      </template>

      <UiTable
        :columns="columns"
        :rows="users"
        :loading="loading"
        empty-title="No hay usuarios todavía"
        empty-body="Cuando alguien se registre, aparecerá aquí."
        @row-click="(u) => $router.push('/admin/users/' + u.id)"
      >
        <template #cell-name="{ row }">
          <div class="flex items-center gap-3">
            <Avatar :name="row.name" size="sm" />
            <div class="min-w-0">
              <div class="font-semibold text-white truncate flex items-center gap-2">
                {{ row.name }}
                <ShieldCheck v-if="row.is_admin" class="w-3.5 h-3.5 text-neon" />
              </div>
              <div class="text-xs text-gray-500 truncate">{{ row.email }}</div>
            </div>
          </div>
        </template>
        <template #cell-joke_calls_count="{ value }">
          <span class="font-mono text-[13px]">{{ value }}</span>
        </template>
        <template #cell-subscription_plan="{ value }">
          <UiBadge v-if="value" :status="''" :color="'#39FF14'" :dot="false">{{ value }}</UiBadge>
          <span v-else class="text-gray-600 text-xs">—</span>
        </template>
        <template #cell-created_at="{ value }">
          <span class="text-gray-500 text-xs">{{ formatDate(value) }}</span>
        </template>
      </UiTable>
    </UiCard>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import { useDebounceFn } from '@vueuse/core'
import { Search, ShieldCheck } from 'lucide-vue-next'

import UiCard from '../components/UiCard.vue'
import UiBadge from '../components/UiBadge.vue'
import UiTable from '../components/UiTable.vue'
import Avatar from '../components/Avatar.vue'

const users = ref([])
const search = ref('')
const loading = ref(true)

const count = computed(() => users.value.length)

const columns = [
  { key: 'name',               label: 'Usuario' },
  { key: 'joke_calls_count',   label: 'Llamadas', align: 'right', width: '110px' },
  { key: 'subscription_plan',  label: 'Plan',     width: '120px' },
  { key: 'created_at',         label: 'Joined',   width: '110px' },
]

const debouncedFetch = useDebounceFn(() => fetchUsers(), 300)

async function fetchUsers() {
  loading.value = true
  try {
    const { data } = await axios.get('/admin-api/users', { params: { search: search.value } })
    users.value = data.data
  } catch {} finally { loading.value = false }
}

function formatDate(d) {
  if (!d) return ''
  return new Date(d).toLocaleDateString('es-MX', { day: 'numeric', month: 'short', year: '2-digit' })
}

onMounted(fetchUsers)
</script>
