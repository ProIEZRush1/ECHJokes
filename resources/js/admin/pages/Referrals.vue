<template>
  <div class="p-6 lg:p-8 space-y-5 max-w-[1400px]">
    <header>
      <div class="text-[11.5px] uppercase tracking-wider text-gray-500 font-semibold mb-1">Sistema</div>
      <h1 class="text-[26px] font-bold tracking-tight">Crecimiento viral</h1>
      <p class="text-sm text-gray-400 mt-1.5">Referrals, K-factor, share funnel.</p>
    </header>

    <div v-if="loading" class="grid grid-cols-2 md:grid-cols-4 gap-3">
      <UiCard v-for="n in 4" :key="n">
        <UiSkeleton h="14px" w="40%" class="mb-2" />
        <UiSkeleton h="32px" w="50%" />
      </UiCard>
    </div>

    <template v-else>
      <!-- Personal link -->
      <UiCard v-if="me" class="border-neon/30">
        <div class="flex items-center justify-between mb-3 flex-wrap gap-2">
          <h2 class="text-sm font-semibold text-neon uppercase tracking-wide">Tu link personal</h2>
          <span class="text-xs text-gray-500">Código: <strong class="font-mono text-neon">{{ me.code }}</strong></span>
        </div>
        <div class="flex flex-wrap gap-2 items-center mb-4">
          <input :value="me.link" readonly class="flex-1 min-w-[260px] bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-xs font-mono text-gray-300 focus:outline-none focus:border-neon/50" />
          <UiButton variant="primary" size="sm" @click="copyLink">
            <Copy class="w-3 h-3" /> {{ copied ? 'Copiado' : 'Copiar' }}
          </UiButton>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-5 gap-2">
          <div class="bg-white/5 border border-white/5 rounded-lg p-3 text-center">
            <div class="text-2xl font-bold font-mono text-neon">{{ me.clicks }}</div>
            <div class="text-[10px] text-gray-500 uppercase mt-1">Clicks</div>
          </div>
          <div class="bg-white/5 border border-white/5 rounded-lg p-3 text-center">
            <div class="text-2xl font-bold font-mono text-white">{{ me.unique_visitors }}</div>
            <div class="text-[10px] text-gray-500 uppercase mt-1">Únicos</div>
          </div>
          <div class="bg-white/5 border border-white/5 rounded-lg p-3 text-center">
            <div class="text-2xl font-bold font-mono text-white">{{ me.signups }}</div>
            <div class="text-[10px] text-gray-500 uppercase mt-1">Registros</div>
          </div>
          <div class="bg-white/5 border border-white/5 rounded-lg p-3 text-center">
            <div class="text-2xl font-bold font-mono text-neon">{{ me.converted }}</div>
            <div class="text-[10px] text-gray-500 uppercase mt-1">Convertidos</div>
          </div>
          <div class="bg-white/5 border border-white/5 rounded-lg p-3 text-center">
            <div class="text-2xl font-bold font-mono text-white">{{ me.conversion_rate }}%</div>
            <div class="text-[10px] text-gray-500 uppercase mt-1">Tasa registro</div>
          </div>
        </div>
      </UiCard>

      <!-- Top-line metrics -->
      <div v-if="stats" class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <UiStatCard :label="'K-factor'"          :value="stats.k_factor"     :hot="parseFloat(stats.k_factor) >= 1" :icon="TrendingUp" hint="Meta: > 1.0" />
        <UiStatCard :label="'Usuarios activos'"   :value="stats.active_users" :icon="Users" />
        <UiStatCard :label="'Referidos (total)'"  :value="stats.with_referrer" :icon="Gift" />
        <UiStatCard :label="'Cycle time (días)'"  :value="stats.avg_cycle_days ?? '—'" :icon="Clock" hint="Meta: < 7" />
      </div>

      <!-- K-factor trend -->
      <UiCard v-if="kFactorSeries?.length" title="K-factor trend">
        <div style="height:240px">
          <Line :data="kFactorChart" :options="kFactorOptions" />
        </div>
      </UiCard>

      <!-- Funnel + channels -->
      <div v-if="viral" class="grid md:grid-cols-2 gap-4">
        <UiCard title="Share funnel" subtitle="Meta: share rate > 40%">
          <div class="space-y-2.5 text-sm">
            <div class="flex justify-between"><span class="text-gray-400">Llamadas totales</span><span class="text-white font-semibold font-mono">{{ viral.share_funnel.total_calls }}</span></div>
            <div class="flex justify-between"><span class="text-gray-400">Llamadas públicas</span><span class="text-white font-semibold font-mono">{{ viral.share_funnel.public_calls }}</span></div>
            <div class="flex justify-between"><span class="text-gray-400">Llamadas con vistas</span><span class="text-white font-semibold font-mono">{{ viral.share_funnel.calls_with_views }}</span></div>
            <div class="flex justify-between"><span class="text-gray-400">Vistas totales</span><span class="text-neon font-bold font-mono">{{ viral.share_funnel.total_share_views }}</span></div>
            <div class="flex justify-between"><span class="text-gray-400">Share rate</span><span class="text-neon font-bold font-mono">{{ viral.share_funnel.share_rate }}%</span></div>
          </div>
          <div class="mt-3 h-2 bg-white/5 rounded-full overflow-hidden">
            <div class="h-full bg-neon transition-all" :style="`width: ${Math.min(100, viral.share_funnel.share_rate || 0)}%`" />
          </div>
        </UiCard>

        <UiCard title="Tráfico por canal">
          <div v-if="viral.channels.length" class="space-y-2.5 text-sm">
            <div v-for="c in viral.channels" :key="c.utm_source" class="flex justify-between">
              <span class="text-gray-400">{{ c.utm_source }}</span>
              <span class="text-gray-300"><strong class="text-neon font-mono">{{ c.touches }}</strong> touches · {{ c.users }} users</span>
            </div>
          </div>
          <UiEmptyState v-else :icon="Share2" title="Sin datos aún" body="Comparte tu link con UTMs para empezar a medir." />
        </UiCard>
      </div>

      <!-- WhatsApp A/B -->
      <UiCard v-if="viral?.whatsapp_ab?.length" title="A/B test — mensaje WhatsApp">
        <table class="w-full text-sm">
          <thead>
            <tr class="text-xs text-gray-500 uppercase border-b border-white/10">
              <th class="text-left py-2">Variante</th>
              <th class="text-right py-2">Vistas</th>
              <th class="text-right py-2">Clicks</th>
              <th class="text-right py-2">CTR</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="v in viral.whatsapp_ab" :key="v.variant" class="border-b border-white/5 last:border-0">
              <td class="py-2 font-semibold text-neon">{{ v.variant }}</td>
              <td class="py-2 text-right font-mono">{{ v.impressions }}</td>
              <td class="py-2 text-right font-mono">{{ v.clicks }}</td>
              <td class="py-2 text-right font-bold font-mono">{{ v.ctr }}%</td>
            </tr>
          </tbody>
        </table>
      </UiCard>

      <!-- Leaderboard -->
      <UiCard title="Top referidores" :padded="false">
        <UiTable
          :columns="topColumns"
          :rows="top"
          empty-title="Aún no hay referidos"
          empty-body="Cuando alguien invite y convierta, aparecerá aquí."
        >
          <template #cell-rank="{ row }">
            <span class="text-lg">{{ rankIcon(top.indexOf(row)) }}</span>
          </template>
          <template #cell-user="{ row }">
            <div class="flex items-center gap-2">
              <Avatar :name="row.name" size="xs" />
              <div>
                <div class="text-white text-[13px]">{{ row.name }}</div>
                <div class="text-[11px] text-gray-500">{{ row.email }}</div>
              </div>
            </div>
          </template>
          <template #cell-referral_code="{ value }">
            <span class="font-mono text-neon text-[12px]">{{ value }}</span>
          </template>
          <template #cell-credits="{ row }">
            <span class="font-mono text-neon font-bold">{{ row.converted_count * 2 }}</span>
          </template>
        </UiTable>
      </UiCard>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import {
  Copy, Users, Gift, Clock, TrendingUp, Share2,
} from 'lucide-vue-next'

import UiCard from '../components/UiCard.vue'
import UiButton from '../components/UiButton.vue'
import UiSkeleton from '../components/UiSkeleton.vue'
import UiStatCard from '../components/UiStatCard.vue'
import UiEmptyState from '../components/UiEmptyState.vue'
import UiTable from '../components/UiTable.vue'
import Avatar from '../components/Avatar.vue'
import { useToast } from '../composables/useToast.js'

import { Chart, LineElement, PointElement, LinearScale, CategoryScale, Filler, Tooltip } from 'chart.js'
import { Line } from 'vue-chartjs'
Chart.register(LineElement, PointElement, LinearScale, CategoryScale, Filler, Tooltip)

const toast = useToast()
const stats = ref(null)
const top = ref([])
const viral = ref(null)
const me = ref(null)
const copied = ref(false)
const loading = ref(true)

const topColumns = [
  { key: 'rank',            label: '#',        width: '50px' },
  { key: 'user',            label: 'Usuario' },
  { key: 'referral_code',   label: 'Código',   width: '100px' },
  { key: 'referred_count',  label: 'Invitados',  align: 'right', width: '90px' },
  { key: 'converted_count', label: 'Convertidos', align: 'right', width: '110px' },
  { key: 'credits',         label: 'Créditos', align: 'right', width: '90px' },
]

function rankIcon(i) {
  return ['🥇','🥈','🥉'][i] || `#${i+1}`
}

function copyLink() {
  if (!me.value || !navigator.clipboard) return
  navigator.clipboard.writeText(me.value.link)
  copied.value = true
  toast.success('Link copiado')
  setTimeout(() => { copied.value = false }, 2000)
}

const kFactorSeries = computed(() => stats.value?.k_factor_history || null)
const kFactorChart = computed(() => ({
  labels: (kFactorSeries.value || []).map((_, i) => i),
  datasets: [{
    data: (kFactorSeries.value || []).map(p => p.value ?? p),
    borderColor: '#39FF14',
    borderWidth: 2.5,
    tension: 0.3,
    pointRadius: 0,
    fill: true,
    backgroundColor: (ctx) => {
      const c = ctx.chart.ctx
      const g = c.createLinearGradient(0, 0, 0, 240)
      g.addColorStop(0, 'rgba(57,255,20,0.35)')
      g.addColorStop(1, 'rgba(57,255,20,0)')
      return g
    },
  }, {
    data: (kFactorSeries.value || []).map(() => 1),
    borderColor: 'rgba(255,255,255,0.25)',
    borderWidth: 1,
    borderDash: [4, 4],
    pointRadius: 0,
    fill: false,
  }],
}))
const kFactorOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: { legend: { display: false }, tooltip: { mode: 'index', intersect: false } },
  scales: {
    x: { display: false },
    y: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#9aa0a6', font: { size: 10 } } },
  },
}

onMounted(async () => {
  try {
    const [{ data: r }, { data: v }] = await Promise.all([
      axios.get('/admin-api/referrals'),
      axios.get('/admin-api/viral-metrics'),
    ])
    stats.value = r.stats
    top.value   = r.top || []
    me.value    = r.me || null
    viral.value = v
  } catch (e) { toast.error(e) } finally { loading.value = false }
})
</script>
