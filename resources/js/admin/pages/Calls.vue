<template>
  <div class="p-6 lg:p-8 space-y-5 max-w-[1400px]">
    <header class="flex items-end justify-between gap-4 flex-wrap">
      <div>
        <div class="text-[11.5px] uppercase tracking-wider text-gray-500 font-semibold mb-1">Operación</div>
        <h1 class="text-[26px] font-bold tracking-tight">Llamadas</h1>
        <p class="text-sm text-gray-400 mt-1.5">{{ meta.total ?? '—' }} resultados.</p>
      </div>
      <UiButton variant="primary" size="md" @click="$router.push('/admin/launch')">
        <Plus class="w-4 h-4" /> Nueva llamada
      </UiButton>
    </header>

    <AdBanner :slot="AD_SLOTS.callsHeader" format="leaderboard" />

    <UiCard :padded="false">
      <template #header>
        <div class="flex flex-wrap items-center gap-3">
          <div class="relative w-72 max-w-[60vw]">
            <Search class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-500" />
            <input
              v-model="search"
              @input="debouncedFetch"
              placeholder="Buscar por número o escenario…"
              class="w-full bg-white/5 border border-white/10 rounded-lg pl-9 pr-3 py-1.5 text-sm text-white placeholder:text-gray-500 focus:outline-none focus:border-neon/50 transition"
            />
          </div>

          <!-- Status filter chips -->
          <div class="flex items-center gap-1.5 flex-wrap">
            <button
              v-for="s in statusOptions"
              :key="s.value || 'all'"
              @click="setStatus(s.value)"
              :class="[
                'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11.5px] font-semibold border transition',
                statusFilter === s.value
                  ? 'bg-neon/15 border-neon/40 text-neon'
                  : 'bg-white/5 border-white/10 text-gray-400 hover:text-white hover:border-white/20',
              ]"
            >
              {{ s.label }}
            </button>
          </div>
        </div>
      </template>

      <UiTable
        :columns="columns"
        :rows="calls"
        :loading="loading"
        :pagination="{ currentPage: meta.current_page || 1, lastPage: meta.last_page || 1, total: meta.total || 0 }"
        empty-title="No hay llamadas todavía"
        empty-body="Cuando se hagan vaciladas, aparecen aquí."
        @row-click="(c) => $router.push('/admin/calls/' + c.id)"
        @page="changePage"
      >
        <template #cell-phone_number="{ value }">
          <PhoneCell :phone="value" class="group" :mask="false" />
        </template>
        <template #cell-delivery_type="{ value }">
          <UiBadge
            :status="''"
            :color="value === 'joke_call' ? '#ffc14d' : '#7ec3ff'"
            :dot="false"
          >{{ value === 'joke_call' ? 'Joke' : 'Prank' }}</UiBadge>
        </template>
        <template #cell-custom_joke_prompt="{ row }">
          <span class="text-gray-300 line-clamp-1 max-w-[320px] inline-block align-middle">
            {{ row.custom_joke_prompt || row.joke_text || '—' }}
          </span>
        </template>
        <template #cell-status="{ value }">
          <UiBadge :status="value" :pulse="value === 'in_progress' || value === 'calling'" />
        </template>
        <template #cell-source="{ row }">
          <UiBadge
            :status="''"
            :color="sourceColor(effectiveSource(row))"
            :dot="false"
          >{{ sourceLabel(effectiveSource(row)) }}</UiBadge>
        </template>
        <template #cell-user="{ row }">
          <span v-if="row.user" class="inline-flex items-center gap-2 text-gray-300 text-[13px] hover:text-neon transition" @click.stop="$router.push('/admin/users/' + row.user.id)">
            <Avatar :name="row.user.name" size="xs" />{{ row.user.name }}
          </span>
          <span v-else class="text-gray-600 text-xs">—</span>
        </template>
        <template #cell-call_duration_seconds="{ value }">
          <span class="font-mono text-[13px]">{{ formatDuration(value) }}</span>
        </template>
        <template #cell-recording="{ row }">
          <Mic v-if="row.recording_url" class="w-3.5 h-3.5 text-neon" />
          <MicOff v-else class="w-3.5 h-3.5 text-gray-700" />
        </template>
        <template #cell-created_at="{ value }">
          <span class="text-gray-500 text-xs whitespace-nowrap">{{ formatDate(value) }}</span>
        </template>
      </UiTable>
    </UiCard>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import { useDebounceFn } from '@vueuse/core'
import { Plus, Search, Mic, MicOff } from 'lucide-vue-next'

import AdBanner from '../../components/AdBanner.vue'
import { AD_SLOTS } from '../../adsense.js'
import UiCard from '../components/UiCard.vue'
import UiButton from '../components/UiButton.vue'
import UiBadge from '../components/UiBadge.vue'
import UiTable from '../components/UiTable.vue'
import Avatar from '../components/Avatar.vue'
import PhoneCell from '../components/PhoneCell.vue'

const calls = ref([])
const meta = ref({})
const search = ref('')
const statusFilter = ref('')
const page = ref(1)
const loading = ref(false)

const statusOptions = [
  { value: '',            label: 'Todas' },
  { value: 'completed',   label: 'Completed' },
  { value: 'in_progress', label: 'En curso' },
  { value: 'calling',     label: 'Calling' },
  { value: 'voicemail',   label: 'Voicemail' },
  { value: 'failed',      label: 'Failed' },
]

const columns = [
  { key: 'phone_number',          label: 'Phone',     mono: true,  width: '160px' },
  { key: 'delivery_type',         label: 'Type',      width: '90px' },
  { key: 'custom_joke_prompt',    label: 'Scenario' },
  { key: 'status',                label: 'Status',    width: '140px' },
  { key: 'source',                label: 'Source',    width: '100px' },
  { key: 'user',                  label: 'User',      width: '160px' },
  { key: 'call_duration_seconds', label: 'Dur',       align: 'right', width: '70px' },
  { key: 'recording',             label: 'Rec',       align: 'center', width: '50px' },
  { key: 'created_at',            label: 'Date',      width: '130px' },
]

const debouncedFetch = useDebounceFn(() => { page.value = 1; fetchCalls() }, 300)

function setStatus(value) {
  statusFilter.value = value
  page.value = 1
  fetchCalls()
}

function changePage(p) {
  page.value = p
  fetchCalls()
}

async function fetchCalls() {
  loading.value = true
  try {
    const { data } = await axios.get('/admin-api/calls', {
      params: { page: page.value, search: search.value, status: statusFilter.value },
    })
    calls.value = data.data
    meta.value = { current_page: data.current_page, last_page: data.last_page, total: data.total }
  } catch {} finally { loading.value = false }
}

function sourceColor(s) {
  return { trial: '#7ec3ff', paid: '#39FF14', custom: '#c89bff', referral: '#ffc14d' }[s] || '#9aa0a6'
}
function sourceLabel(s) {
  return { trial: 'Trial', paid: 'Paid', custom: 'Admin', prank: 'Admin', referral: 'Referral' }[s] || s || '—'
}
function effectiveSource(call) {
  if (call.joke_source !== 'paid') return call.joke_source
  const u = call.user
  if (u?.referred_by_user_id && !u?.subscription_plan) return 'referral'
  return 'paid'
}
function formatDuration(s) { return s ? `${String(Math.floor(s/60)).padStart(2,'0')}:${String(s%60).padStart(2,'0')}` : '—' }
function formatDate(d) { return d ? new Date(d).toLocaleString('es-MX', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }) : '' }

onMounted(fetchCalls)
</script>
