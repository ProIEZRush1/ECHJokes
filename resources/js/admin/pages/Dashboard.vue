<template>
  <div class="p-6 lg:p-8 space-y-6 max-w-[1400px]">
    <!-- Header -->
    <header class="flex items-end justify-between gap-4">
      <div>
        <div class="text-[11.5px] uppercase tracking-wider text-gray-500 font-semibold mb-1">
          Operación · {{ todayLabel }}
        </div>
        <h1 class="text-[28px] font-bold tracking-tight">{{ greeting }}</h1>
        <p class="text-sm text-gray-400 mt-1.5">Aquí el pulso del día. Auto-refresh cada 10 s.</p>
      </div>
      <div class="hidden md:flex items-center gap-2 text-[12.5px] text-gray-400">
        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full border border-neon/30 bg-neon/5 text-neon font-semibold">
          <span class="w-1.5 h-1.5 rounded-full bg-neon animate-[pulse-dot_1.6s_ease-out_infinite]" />
          En vivo
        </span>
      </div>
    </header>

    <!-- Stat cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
      <UiStatCard
        label="Calls today"
        :value="stats.calls_today ?? 0"
        :delta="deltaPct"
        :icon="Phone"
        :hot="true"
        :spark="todaySpark"
        hint="vs ayer"
      />
      <UiStatCard
        label="Completadas"
        :value="stats.completed_today ?? 0"
        :icon="CheckCircle2"
        :hint="successRateHint"
      />
      <UiStatCard
        label="Active now"
        :value="stats.active_now ?? 0"
        :icon="Activity"
        :hot="(stats.active_now ?? 0) > 0"
        :live-dot="(stats.active_now ?? 0) > 0"
        hint="en línea ahora"
      />
      <UiStatCard
        label="Total calls"
        :value="stats.total_calls ?? 0"
        :icon="BarChart3"
        hint="desde lanzamiento"
      />
    </div>

    <!-- Recent calls -->
    <UiCard :padded="false">
      <template #header>
        <h2 class="text-[15px] font-semibold tracking-tight text-white">Recent calls</h2>
        <p class="text-xs text-gray-500 mt-0.5">Últimas 8 llamadas · click para abrir</p>
      </template>
      <template #actions>
        <UiButton variant="ghost" size="sm" @click="$router.push('/admin/calls')">
          Ver todas <ArrowRight class="w-3.5 h-3.5" />
        </UiButton>
      </template>

      <UiTable
        :columns="columns"
        :rows="recentCalls"
        :loading="loading"
        empty-title="Aún no hay llamadas"
        empty-body="Cuando se hagan vaciladas, aparecen aquí."
        @row-click="(row) => $router.push('/admin/calls/' + row.id)"
      >
        <template #cell-phone_number="{ value }">
          <PhoneCell :phone="value" class="group" />
        </template>
        <template #cell-custom_joke_prompt="{ value }">
          <span class="text-gray-300 line-clamp-1">{{ value || '—' }}</span>
        </template>
        <template #cell-status="{ value }">
          <UiBadge :status="value" />
        </template>
        <template #cell-call_duration_seconds="{ value }">
          <span class="font-mono text-[13px] text-gray-300">{{ formatDuration(value) }}</span>
        </template>
        <template #cell-created_at="{ value }">
          <span class="text-gray-500 text-[12.5px]">{{ timeAgo(value) }}</span>
        </template>
      </UiTable>
    </UiCard>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import axios from 'axios'
import {
  Phone, CheckCircle2, Activity, BarChart3, ArrowRight,
} from 'lucide-vue-next'

import UiCard      from '../components/UiCard.vue'
import UiButton    from '../components/UiButton.vue'
import UiBadge     from '../components/UiBadge.vue'
import UiStatCard  from '../components/UiStatCard.vue'
import UiTable     from '../components/UiTable.vue'
import PhoneCell   from '../components/PhoneCell.vue'

const stats = ref({})
const recentCalls = ref([])
const loading = ref(true)
let interval

const columns = [
  { key: 'phone_number',          label: 'Phone',    mono: true,  width: '170px' },
  { key: 'custom_joke_prompt',    label: 'Scenario' },
  { key: 'status',                label: 'Status',   width: '160px' },
  { key: 'call_duration_seconds', label: 'Dur',      width: '70px', align: 'right' },
  { key: 'created_at',            label: 'Time',     width: '90px' },
]

const greeting = computed(() => {
  const h = new Date().getHours()
  const part = h < 12 ? 'Buen día' : h < 19 ? 'Buenas tardes' : 'Buenas noches'
  return `${part}.`
})
const todayLabel = computed(() => new Date().toLocaleDateString('es-MX', { weekday: 'long', day: 'numeric', month: 'short' }))

const deltaPct = computed(() => {
  const t = stats.value.calls_today, y = stats.value.calls_yesterday
  if (!t || !y || y === 0) return null
  return Math.round(((t - y) / y) * 100)
})
const successRateHint = computed(() => {
  const t = stats.value.calls_today, c = stats.value.completed_today
  if (!t || t === 0) return ''
  return Math.round((c / t) * 100) + ' % éxito'
})
const todaySpark = computed(() => stats.value.hourly_today || null)

function formatDuration(s) {
  if (!s) return '—'
  const m = Math.floor(s / 60), r = s % 60
  return String(m).padStart(2,'0') + ':' + String(r).padStart(2,'0')
}
function timeAgo(d) {
  if (!d) return ''
  const ms = Date.now() - new Date(d).getTime()
  const s = Math.floor(ms / 1000)
  if (s < 60) return 'ahora'
  if (s < 3600) return Math.floor(s / 60) + ' min'
  if (s < 86400) return Math.floor(s / 3600) + ' h'
  return new Date(d).toLocaleDateString('es-MX')
}

async function fetchData() {
  try {
    const [s, c] = await Promise.all([
      axios.get('/admin-api/stats'),
      axios.get('/admin-api/calls', { params: { per_page: 8 } }),
    ])
    stats.value = s.data
    recentCalls.value = c.data.data
  } catch {} finally { loading.value = false }
}

onMounted(() => {
  fetchData()
  interval = setInterval(fetchData, 10000)
})
onUnmounted(() => clearInterval(interval))
</script>
