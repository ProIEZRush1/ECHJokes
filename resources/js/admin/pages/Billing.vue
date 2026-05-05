<template>
  <div class="p-6 lg:p-8 space-y-5 max-w-[1400px]">
    <header>
      <div class="text-[11.5px] uppercase tracking-wider text-gray-500 font-semibold mb-1">Sistema</div>
      <h1 class="text-[26px] font-bold tracking-tight">Billing & Usage</h1>
      <p class="text-sm text-gray-400 mt-1.5">Saldos, gasto del mes, costo por minuto, ingresos.</p>
    </header>

    <div v-if="loading" class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <UiCard v-for="n in 3" :key="n">
        <UiSkeleton h="14px" w="40%" class="mb-3" />
        <UiSkeleton h="32px" w="60%" class="mb-2" />
        <UiSkeleton h="12px" w="50%" />
      </UiCard>
    </div>

    <template v-else>
      <!-- Low balance banner -->
      <UiCard v-if="anyAlert" class="border-amber-500/30 bg-amber-500/5">
        <div class="flex items-start gap-3">
          <AlertTriangle class="w-5 h-5 text-amber-400 flex-shrink-0 mt-0.5" />
          <div class="flex-1 text-sm">
            <p class="font-semibold text-amber-300">Atención necesaria</p>
            <ul class="list-disc list-inside mt-1 text-amber-200/80 space-y-0.5">
              <li v-if="twilioLow">Twilio balance bajo (${{ data.twilio?.balance }}). Recarga para no parar llamadas.</li>
              <li v-if="elLow">ElevenLabs casi sin caracteres ({{ ((data.elevenlabs?.characters_remaining || 0)/1000).toFixed(0) }}K).</li>
              <li v-if="openAiQuotaFail">OpenAI tuvo fallas de cuota recientemente · {{ lastFailRelative }}.</li>
            </ul>
          </div>
        </div>
      </UiCard>

      <!-- API balances -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <UiCard hover>
          <p class="text-[11.5px] text-gray-500 uppercase tracking-wide font-semibold mb-1">Twilio</p>
          <p class="text-2xl font-bold font-mono" :class="twilioLow ? 'text-red-400' : 'text-neon'">
            {{ data.twilio?.balance ? `$${parseFloat(data.twilio.balance).toFixed(2)}` : 'N/A' }}
          </p>
          <p class="text-xs text-gray-500 mt-1">{{ data.twilio?.currency || 'USD' }}</p>
          <p v-if="twilioLow" class="text-xs text-red-400 mt-2">⚠ saldo bajo · recarga</p>
        </UiCard>

        <UiCard hover :class="openAiQuotaFail ? 'border-red-500/40' : ''">
          <div class="flex items-start justify-between mb-1">
            <p class="text-[11.5px] text-gray-500 uppercase tracking-wide font-semibold">OpenAI</p>
            <a :href="data.openai?.dashboard_url" target="_blank" class="text-[10px] text-gray-500 hover:text-neon">Abrir ↗</a>
          </div>
          <template v-if="data.openai?.spent_month_usd !== undefined">
            <p class="text-2xl font-bold font-mono text-amber-400">${{ data.openai.spent_month_usd.toFixed(2) }}</p>
            <p class="text-xs text-gray-500 mt-1">gastado este mes</p>

            <!-- daily-spend sparkline -->
            <div v-if="data.openai.daily_spend?.length" class="mt-3" style="height:48px">
              <Line :data="openAiDailyChart" :options="sparkOptions" />
            </div>

            <div v-if="data.openai.input_tokens_month !== undefined" class="mt-2 pt-2 border-t border-white/5 text-[10.5px] text-gray-500 space-y-0.5">
              <div class="flex justify-between"><span>tokens entrada</span><span class="font-mono text-gray-400">{{ formatNum(data.openai.input_tokens_month) }}</span></div>
              <div class="flex justify-between"><span>tokens salida</span><span class="font-mono text-gray-400">{{ formatNum(data.openai.output_tokens_month) }}</span></div>
              <div v-if="data.openai.audio_seconds_month" class="flex justify-between"><span>audio</span><span class="font-mono text-gray-400">{{ Math.round(data.openai.audio_seconds_month / 60) }} min</span></div>
            </div>
          </template>
          <template v-else>
            <p class="text-sm text-gray-500 mt-1">Balance no expuesto por la API</p>
            <p class="text-[10px] text-gray-600 mt-1">Añade <code class="text-gray-400">OPENAI_ADMIN_KEY</code> para ver gasto mensual.</p>
          </template>
          <p v-if="openAiQuotaFail" class="text-xs text-red-400 mt-2">⚠ quota fail · {{ lastFailRelative }}</p>
        </UiCard>

        <UiCard hover>
          <p class="text-[11.5px] text-gray-500 uppercase tracking-wide font-semibold mb-1">ElevenLabs</p>
          <template v-if="data.elevenlabs && !data.elevenlabs.error">
            <p class="text-2xl font-bold font-mono" :class="elLow ? 'text-red-400' : 'text-blue-400'">
              {{ ((data.elevenlabs.characters_remaining || 0) / 1000).toFixed(0) }}K
            </p>
            <p class="text-xs text-gray-500 mt-1">chars · {{ data.elevenlabs.tier }}</p>
            <div class="mt-2 h-1.5 bg-white/5 rounded-full overflow-hidden">
              <div
                class="h-full rounded-full transition-all"
                :class="elLow ? 'bg-red-400' : 'bg-blue-400'"
                :style="`width: ${Math.min(100, (data.elevenlabs.characters_remaining / data.elevenlabs.characters_limit) * 100)}%`"
              />
            </div>
          </template>
          <p v-else class="text-sm text-gray-500">N/A</p>
        </UiCard>
      </div>

      <AdBanner :slot="AD_SLOTS.billingMiddle" format="leaderboard" />

      <!-- Calls breakdown -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <UiStatCard label="Total calls"     :value="data.calls?.total ?? 0"     :icon="Phone" />
        <UiStatCard label="Admin (free)"    :value="data.calls?.admin ?? 0"     :icon="ShieldCheck" />
        <UiStatCard label="Trial (free)"    :value="data.calls?.trial ?? 0"     :icon="Gift" />
        <UiStatCard label="Paid"            :value="data.calls?.paid ?? 0"      :icon="DollarSign" :hot="true" />
      </div>

      <!-- Cost & revenue -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <UiCard title="Today">
          <div class="grid grid-cols-2 gap-3">
            <div>
              <p class="text-xs text-gray-500 uppercase">Calls</p>
              <p class="font-bold font-mono text-xl">{{ data.calls?.today }}</p>
            </div>
            <div>
              <p class="text-xs text-gray-500 uppercase">Completed</p>
              <p class="font-bold font-mono text-xl text-neon">{{ data.calls?.completed_today }}</p>
            </div>
          </div>
        </UiCard>

        <UiCard title="Este mes">
          <div class="grid grid-cols-3 gap-3">
            <div>
              <p class="text-xs text-gray-500 uppercase">Calls</p>
              <p class="font-bold font-mono text-xl">{{ data.calls?.this_month }}</p>
            </div>
            <div>
              <p class="text-xs text-gray-500 uppercase">Completed</p>
              <p class="font-bold font-mono text-xl text-neon">{{ data.calls?.completed_month }}</p>
            </div>
            <div>
              <p class="text-xs text-gray-500 uppercase">Min</p>
              <p class="font-bold font-mono text-xl">{{ data.minutes?.this_month }}</p>
            </div>
          </div>
        </UiCard>
      </div>

      <UiCard title="Cost summary">
        <table class="w-full text-sm">
          <tbody>
            <tr class="border-b border-white/5">
              <td class="py-2.5 text-gray-400">Costo por minuto (Twilio + OpenAI + ElevenLabs)</td>
              <td class="py-2.5 text-right font-mono">${{ data.costs?.cost_per_minute_usd }}/min</td>
            </tr>
            <tr class="border-b border-white/5">
              <td class="py-2.5 text-gray-400">Minutos totales (all time)</td>
              <td class="py-2.5 text-right font-mono">{{ data.minutes?.total }} min</td>
            </tr>
            <tr class="border-b border-white/5">
              <td class="py-2.5 text-gray-400">Costo total estimado</td>
              <td class="py-2.5 text-right font-mono text-amber-400">${{ data.costs?.estimated_total_usd }}</td>
            </tr>
            <tr>
              <td class="py-2.5 text-gray-400">Estimado este mes</td>
              <td class="py-2.5 text-right font-mono text-amber-400">${{ data.costs?.estimated_month_usd }}</td>
            </tr>
            <tr class="border-t border-white/10 bg-neon/5">
              <td class="py-2.5 text-gray-300 font-semibold">Revenue mes</td>
              <td class="py-2.5 text-right font-mono text-neon font-bold">${{ data.revenue_mxn || '0' }} MXN
                <span class="text-[11px] text-gray-500 font-normal ml-2">neto ${{ data.revenue_net_mxn || '0' }}</span>
              </td>
            </tr>
          </tbody>
        </table>
      </UiCard>

      <UiCard title="Otros servicios" subtitle="Estado de configuración">
        <div class="grid grid-cols-2 gap-3 text-sm">
          <div class="flex items-center gap-2">
            <Check v-if="data.anthropic?.configured" class="w-4 h-4 text-neon" />
            <X v-else class="w-4 h-4 text-gray-600" />
            <span>Anthropic (moderación)</span>
          </div>
          <div class="flex items-center gap-2">
            <Check v-if="data.api_ninjas?.configured" class="w-4 h-4 text-neon" />
            <X v-else class="w-4 h-4 text-gray-600" />
            <span>api-ninjas (chistes)</span>
          </div>
        </div>
      </UiCard>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import {
  AlertTriangle, Phone, ShieldCheck, Gift, DollarSign, Check, X,
} from 'lucide-vue-next'

import AdBanner from '../../components/AdBanner.vue'
import { AD_SLOTS } from '../../adsense.js'
import UiCard from '../components/UiCard.vue'
import UiSkeleton from '../components/UiSkeleton.vue'
import UiStatCard from '../components/UiStatCard.vue'
import { useToast } from '../composables/useToast.js'

import { Chart, LineElement, PointElement, LinearScale, CategoryScale, Filler, Tooltip } from 'chart.js'
import { Line } from 'vue-chartjs'
Chart.register(LineElement, PointElement, LinearScale, CategoryScale, Filler, Tooltip)

const toast = useToast()
const data = ref({})
const loading = ref(true)

const twilioLow = computed(() => parseFloat(data.value.twilio?.balance || 0) < 10)
const elLow = computed(() => (data.value.elevenlabs?.characters_remaining || 0) < 10000)
const openAiQuotaFail = computed(() => {
  const ts = data.value.openai?.last_ai_failure_at
  if (!ts) return false
  return (Date.now() - new Date(ts).getTime()) < 24 * 60 * 60 * 1000
})
const anyAlert = computed(() => twilioLow.value || elLow.value || openAiQuotaFail.value)

function formatNum(n) {
  if (n === undefined || n === null) return '0'
  if (n >= 1_000_000) return (n / 1_000_000).toFixed(1) + 'M'
  if (n >= 1_000) return (n / 1_000).toFixed(1) + 'K'
  return String(n)
}

const lastFailRelative = computed(() => {
  const ts = data.value.openai?.last_ai_failure_at
  if (!ts) return ''
  const mins = Math.max(1, Math.floor((Date.now() - new Date(ts).getTime()) / 60000))
  if (mins < 60) return `hace ${mins} min`
  const h = Math.floor(mins / 60)
  return h < 24 ? `hace ${h}h` : `hace ${Math.floor(h / 24)}d`
})

const openAiDailyChart = computed(() => {
  const arr = data.value.openai?.daily_spend || []
  return {
    labels: arr.map((_, i) => i),
    datasets: [{
      data: arr,
      borderColor: '#fbbf24',
      borderWidth: 2,
      tension: 0.35,
      fill: true,
      backgroundColor: (ctx) => {
        const c = ctx.chart.ctx
        const g = c.createLinearGradient(0, 0, 0, 60)
        g.addColorStop(0, 'rgba(251,191,36,0.35)')
        g.addColorStop(1, 'rgba(251,191,36,0)')
        return g
      },
      pointRadius: 0,
    }],
  }
})

const sparkOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: { legend: { display: false }, tooltip: { enabled: false } },
  scales: { x: { display: false }, y: { display: false } },
  elements: { line: { borderJoinStyle: 'round' } },
}

onMounted(async () => {
  try {
    const { data: d } = await axios.get('/admin-api/billing')
    data.value = d
  } catch (e) { toast.error(e) } finally { loading.value = false }
})
</script>
