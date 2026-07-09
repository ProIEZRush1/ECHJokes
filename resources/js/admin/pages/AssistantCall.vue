<template>
  <div class="p-6 lg:p-8 max-w-5xl space-y-6">
    <header>
      <div class="text-[11.5px] uppercase tracking-wider text-gray-500 font-semibold mb-1">Operación</div>
      <h1 class="text-[26px] font-bold tracking-tight">Asistente IA</h1>
      <p class="text-sm text-gray-400 mt-1.5">
        La IA llama a una empresa por ti, se hace pasar por ti, navega el menú telefónico marcando números,
        y hace el trámite. Si tiene una duda, te la pregunta en vivo aquí y tú respondes al instante.
      </p>
    </header>

    <!-- Live call(s) in progress — jump straight to the console to listen -->
    <button
      v-for="c in liveCalls"
      :key="c.id"
      @click="$router.push('/admin/assistant/' + c.id)"
      class="w-full flex items-center gap-3 rounded-xl border border-red-500/40 bg-red-500/10 px-4 py-3 text-left hover:bg-red-500/15 transition"
    >
      <span class="relative flex h-2.5 w-2.5 shrink-0">
        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75" />
        <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-red-500" />
      </span>
      <div class="min-w-0 flex-1">
        <div class="text-sm font-semibold text-white">Llamada en vivo · {{ c.assistant_company || c.phone_number }}</div>
        <div class="text-[11px] text-red-200/80 truncate">{{ c.assistant_objective || 'Toca para abrir la consola y escuchar' }}</div>
      </div>
      <span class="inline-flex items-center gap-1.5 text-red-200 text-sm font-semibold shrink-0">
        <Headphones class="w-4 h-4" /> Escuchar
      </span>
    </button>

    <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
      <!-- Launch form -->
      <UiCard class="lg:col-span-3" title="Nueva llamada de asistente">
        <form @submit.prevent="launch" class="space-y-4">
          <div>
            <label class="block text-[11.5px] uppercase tracking-wide text-gray-400 font-semibold mb-1.5">Teléfono de la empresa</label>
            <div class="flex items-stretch bg-white/5 border border-white/10 rounded-lg overflow-hidden focus-within:border-neon/50 transition">
              <span class="px-3 py-2.5 text-gray-400 font-mono border-r border-white/10 text-sm">+52</span>
              <input
                v-model="form.phone_number"
                required
                placeholder="8009990000"
                inputmode="numeric"
                autocomplete="tel"
                class="flex-1 bg-transparent px-3 py-2.5 text-sm text-white placeholder-gray-500 focus:outline-none"
                @input="form.phone_number = $event.target.value.replace(/[^0-9]/g, '')"
              />
            </div>
            <p class="text-[10.5px] text-gray-500 mt-1">Ej. Volaris, aerolínea, banco, servicio. Con lada si aplica.</p>
          </div>

          <UiInput
            v-model="form.company"
            label="¿A quién llamas? (empresa)"
            placeholder="Ej: Volaris"
          />

          <UiInput
            v-model="form.identity"
            label="Nombre con el que la IA se identifica (tú)"
            :placeholder="me?.name || 'Tu nombre'"
            hint="La IA dirá este nombre cuando le pregunten de parte de quién."
          />

          <UiTextarea
            v-model="form.objective"
            label="Objetivo — qué debe lograr"
            :rows="3"
            required
            placeholder="Ej: Modificar mi reservación. Quiero separar la reservación de 2 pasajeros en dos reservaciones distintas."
          />

          <UiTextarea
            v-model="form.context"
            label="Datos que la IA puede usar (opcional pero recomendado)"
            :rows="4"
            placeholder="Ej: Número de reservación: ABC123. Pasajeros: Juan Pérez y María López. Vuelo: 15 de agosto MEX→CUN. Mi teléfono: 55..."
            hint="Todo lo que la empresa podría pedir. La IA NO inventa datos: lo que no esté aquí, te lo preguntará en vivo."
          />

          <div>
            <label class="block text-[11.5px] uppercase tracking-wide text-gray-400 font-semibold mb-1.5">Voz</label>
            <div class="grid grid-cols-2 gap-2">
              <button
                v-for="v in voices"
                :key="v.id"
                type="button"
                @click="form.voice = v.id"
                :class="[
                  'flex flex-col items-center p-2 rounded-lg border transition text-xs',
                  form.voice === v.id ? 'border-neon bg-neon/10 text-white' : 'border-white/10 bg-white/5 text-gray-500 hover:border-white/20',
                ]"
              >
                <span>{{ v.emoji }}</span>
                <span>{{ v.label }}</span>
              </button>
            </div>
          </div>

          <UiButton variant="primary" size="lg" type="submit" :loading="loading" :disabled="!form.objective.trim()" class="w-full">
            <PhoneCall class="w-4 h-4" /> Iniciar llamada
          </UiButton>

          <UiCard v-if="result && !result.ok" class="border-red-500/40 bg-red-500/5">
            <p class="text-red-300">{{ result.message }}</p>
          </UiCard>
        </form>
      </UiCard>

      <!-- Recent assistant calls -->
      <div class="lg:col-span-2 space-y-3">
        <UiCard title="Llamadas recientes" :padded="false">
          <div v-if="recent.length" class="divide-y divide-white/5">
            <button
              v-for="c in recent"
              :key="c.id"
              @click="$router.push('/admin/assistant/' + c.id)"
              class="w-full flex items-center gap-3 px-4 py-3 text-left hover:bg-white/5 transition"
            >
              <span
                class="relative flex h-2 w-2 shrink-0"
                :class="isLive(c) ? '' : 'opacity-30'"
              >
                <span v-if="isLive(c)" class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75" />
                <span class="relative inline-flex rounded-full h-2 w-2" :class="isLive(c) ? 'bg-red-500' : 'bg-gray-500'" />
              </span>
              <div class="min-w-0 flex-1">
                <div class="text-sm text-white truncate">{{ c.assistant_company || c.phone_number }}</div>
                <div class="text-[11px] text-gray-500 truncate">{{ c.assistant_objective || c.custom_joke_prompt || '—' }}</div>
              </div>
              <UiBadge :status="c.status" :pulse="isLive(c)" :dot="false" />
            </button>
          </div>
          <UiEmptyState v-else :icon="PhoneCall" title="Sin llamadas todavía" />
        </UiCard>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import { PhoneCall, Headphones } from 'lucide-vue-next'

import UiCard from '../components/UiCard.vue'
import UiButton from '../components/UiButton.vue'
import UiInput from '../components/UiInput.vue'
import UiTextarea from '../components/UiTextarea.vue'
import UiBadge from '../components/UiBadge.vue'
import UiEmptyState from '../components/UiEmptyState.vue'
import { useToast } from '../composables/useToast.js'

const router = useRouter()
const toast = useToast()

// Only the gender is actually used downstream (the real ElevenLabs voice is
// chosen from a pool), so keep the choice honest: male vs female.
const voices = [
  { id: 'ash',   emoji: '👨', label: 'Hombre' },
  { id: 'coral', emoji: '👩', label: 'Mujer' },
]

const me = ref(null)
const form = reactive({ phone_number: '', company: '', identity: '', objective: '', context: '', voice: 'ash' })
const loading = ref(false)
const result = ref(null)
const recent = ref([])
let pollInterval

function isLive(c) { return ['calling', 'in_progress'].includes(c.status) }
const liveCalls = computed(() => recent.value.filter(isLive))

async function fetchRecent() {
  try {
    const { data } = await axios.get('/admin-api/calls', { params: { call_type: 'assistant', per_page: 15 } })
    recent.value = data.data || []
  } catch { /* keep last */ }
}

async function launch() {
  loading.value = true; result.value = null
  try {
    const { data } = await axios.post('/admin-api/launch-assistant-call', { ...form }, { timeout: 45000 })
    toast.success('Llamada iniciada')
    router.push('/admin/assistant/' + data.call_id)
  } catch (e) {
    let msg = e.response?.data?.error
    if (!msg && e.response?.data?.errors) msg = Object.values(e.response.data.errors).flat().join(' · ')
    if (!msg) msg = e.response?.data?.message || (e.code === 'ECONNABORTED' ? 'La solicitud tardó demasiado.' : 'No se pudo iniciar la llamada.')
    result.value = { ok: false, message: msg }
    toast.error(msg)
  } finally { loading.value = false }
}

function applyPrefill() {
  const raw = sessionStorage.getItem('assistant_prefill')
  if (!raw) return
  sessionStorage.removeItem('assistant_prefill')
  try {
    const p = JSON.parse(raw)
    // Stored phone is full (+52…); the input shows the part after the +52 prefix.
    form.phone_number = String(p.phone_number || '').replace(/^\+?52/, '').replace(/[^0-9]/g, '')
    form.objective = p.objective || ''
    form.context = p.context || ''
    form.identity = p.identity || ''
    form.company = p.company || ''
    form.voice = p.voice || 'ash'
    toast.info('Datos precargados — modifica y lanza la llamada')
  } catch {}
}

onMounted(async () => {
  applyPrefill()
  try { const { data } = await axios.get('/admin-api/me'); me.value = data.user } catch {}
  fetchRecent()
  pollInterval = setInterval(fetchRecent, 5000)
})
onUnmounted(() => clearInterval(pollInterval))
</script>
