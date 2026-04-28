<template>
  <div class="p-6 lg:p-8 max-w-3xl">
    <header class="mb-5">
      <div class="text-[11.5px] uppercase tracking-wider text-gray-500 font-semibold mb-1">Operación</div>
      <h1 class="text-[26px] font-bold tracking-tight">Launch Call</h1>
      <p class="text-sm text-gray-400 mt-1.5">Tira una broma o un chiste con un click.</p>
    </header>

    <!-- Mode toggle -->
    <div class="inline-flex p-1 rounded-xl bg-white/5 border border-white/10 mb-6">
      <button
        type="button"
        @click="callType = 'prank'"
        :class="['px-4 py-1.5 rounded-lg text-sm font-bold transition', callType === 'prank' ? 'bg-neon text-matrix-900 shadow-[0_4px_22px_-8px_rgba(57,255,20,0.6)]' : 'text-gray-300 hover:text-white']"
      >Prank Call</button>
      <button
        type="button"
        @click="callType = 'joke'"
        :class="['px-4 py-1.5 rounded-lg text-sm font-bold transition', callType === 'joke' ? 'bg-neon text-matrix-900 shadow-[0_4px_22px_-8px_rgba(57,255,20,0.6)]' : 'text-gray-300 hover:text-white']"
      >Joke Call</button>
    </div>

    <!-- Joke Call -->
    <UiCard v-if="callType === 'joke'">
      <form @submit.prevent="launchJoke" class="space-y-4">
        <UiInput v-model="jokeForm.phone_number" label="Phone number" placeholder="+525512345678" required />

        <div>
          <label class="block text-[11.5px] uppercase tracking-wide text-gray-400 font-semibold mb-1.5">Idioma</label>
          <div class="grid grid-cols-5 gap-2">
            <button
              v-for="l in jokeLanguages"
              :key="l.id"
              type="button"
              @click="jokeForm.language = l.id"
              :class="[
                'flex flex-col items-center p-2 rounded-lg border transition text-xs',
                jokeForm.language === l.id
                  ? 'border-neon bg-neon/10 text-white'
                  : 'border-white/10 bg-white/5 text-gray-500 hover:border-white/20',
              ]"
            >
              <span class="text-lg">{{ l.flag }}</span>
              <span>{{ l.label }}</span>
            </button>
          </div>
        </div>

        <UiButton variant="primary" size="lg" :loading="jokeLoading" type="submit" class="w-full">
          <Rocket class="w-4 h-4" /> Send joke
        </UiButton>

        <UiCard v-if="jokeResult" :class="jokeResult.ok ? 'border-neon/40 bg-neon/5' : 'border-red-500/40 bg-red-500/5'">
          <p :class="jokeResult.ok ? 'text-neon' : 'text-red-300'">{{ jokeResult.message }}</p>
          <p v-if="jokeResult.joke" class="mt-2 text-xs text-gray-400 italic">"{{ jokeResult.joke }}"</p>
          <UiButton v-if="jokeResult.callId" variant="ghost" size="sm" @click="$router.push('/admin/calls/' + jokeResult.callId)" class="mt-2">
            View call <ArrowRight class="w-3 h-3" />
          </UiButton>
        </UiCard>
      </form>
    </UiCard>

    <!-- Prank Call -->
    <UiCard v-else>
      <form @submit.prevent="launch" class="space-y-4">
        <div>
          <label class="block text-[11.5px] uppercase tracking-wide text-gray-400 font-semibold mb-1.5">Phone number</label>
          <div class="flex items-stretch bg-white/5 border border-white/10 rounded-lg overflow-hidden focus-within:border-neon/50 transition">
            <span class="px-3 py-2.5 text-gray-400 font-mono border-r border-white/10 text-sm">+52</span>
            <input
              v-model="form.phone_number"
              required
              placeholder="5512345678"
              maxlength="10"
              inputmode="numeric"
              autocomplete="tel-national"
              class="flex-1 bg-transparent px-3 py-2.5 text-sm text-white placeholder-gray-500 focus:outline-none"
              @input="formatPhone"
              @paste="onPhonePaste"
            />
            <button
              type="button"
              @click="pickContact"
              class="shrink-0 px-3 border-l border-white/10 text-gray-400 hover:text-neon transition"
              title="Seleccionar de tus contactos"
            >
              <ContactRound class="w-5 h-5" />
            </button>
          </div>
          <p class="text-[10.5px] text-gray-500 mt-1">Solo números de México (10 dígitos)</p>
        </div>

        <UiInput v-model="form.victim_name" label="Victim's name (optional)" placeholder="Ej: Juan, María, Sr. López…" hint="Mucho más realista con el nombre" />

        <UiTextarea v-model="form.scenario" label="Prank scenario" :rows="4" placeholder="Describe el escenario de la broma…" required />

        <div>
          <label class="block text-[11.5px] uppercase tracking-wide text-gray-400 font-semibold mb-1.5">
            Speaking style <span class="text-red-400">*</span>
          </label>
          <div class="flex gap-2">
            <input
              v-model="form.character"
              placeholder="Ej: Formal y serio, Chistoso, Nervioso…"
              class="flex-1 bg-white/5 border border-white/10 rounded-lg px-3 py-2.5 text-sm text-white placeholder:text-gray-500 focus:outline-none focus:border-neon/50 transition"
            />
            <UiButton type="button" variant="secondary" size="md" :loading="generating" :disabled="!form.scenario.trim()" @click="generateStyle">
              <Sparkles class="w-3.5 h-3.5" /> Auto IA
            </UiButton>
          </div>
        </div>

        <div v-if="presets.length">
          <label class="block text-[11.5px] uppercase tracking-wide text-gray-400 font-semibold mb-2">Quick presets</label>
          <div class="grid grid-cols-2 lg:grid-cols-3 gap-2">
            <button
              v-for="p in presets"
              :key="p.id"
              type="button"
              @click="usePreset(p)"
              :class="[
                'flex items-center gap-2 p-2 rounded-xl border text-left transition text-xs',
                activePreset === p.id
                  ? 'border-neon bg-neon/10 text-white'
                  : 'border-white/10 bg-white/5 text-gray-400 hover:border-white/20',
              ]"
            >
              <span class="text-lg flex-shrink-0">{{ p.emoji }}</span>
              <span class="truncate">{{ p.label }}</span>
            </button>
          </div>
        </div>

        <UiButton
          variant="primary"
          size="lg"
          type="submit"
          :loading="loading"
          :disabled="!form.character.trim()"
          class="w-full"
        >
          <Rocket class="w-4 h-4" /> Launch call
        </UiButton>

        <UiCard v-if="result" :class="result.ok ? 'border-neon/40 bg-neon/5' : 'border-red-500/40 bg-red-500/5'">
          <p :class="result.ok ? 'text-neon' : 'text-red-300'">{{ result.message }}</p>
          <UiButton v-if="result.callId" variant="ghost" size="sm" @click="$router.push('/admin/calls/' + result.callId)" class="mt-2">
            View call <ArrowRight class="w-3 h-3" />
          </UiButton>
        </UiCard>
      </form>
    </UiCard>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import axios from 'axios'
import { Rocket, Sparkles, ContactRound, ArrowRight } from 'lucide-vue-next'

import UiCard from '../components/UiCard.vue'
import UiButton from '../components/UiButton.vue'
import UiInput from '../components/UiInput.vue'
import UiTextarea from '../components/UiTextarea.vue'
import { useToast } from '../composables/useToast.js'

const toast = useToast()
const callType = ref('prank')
const presets = ref([])
const activePreset = ref(null)
const generating = ref(false)

// Joke
const jokeLanguages = [
  { id: 'es', flag: '🇲🇽', label: 'ES' },
  { id: 'en', flag: '🇺🇸', label: 'EN' },
  { id: 'pt', flag: '🇧🇷', label: 'PT' },
  { id: 'fr', flag: '🇫🇷', label: 'FR' },
  { id: 'de', flag: '🇩🇪', label: 'DE' },
]
const jokeForm = reactive({ phone_number: '', language: 'es' })
const jokeLoading = ref(false)
const jokeResult = ref(null)

async function launchJoke() {
  jokeLoading.value = true; jokeResult.value = null
  try {
    const { data } = await axios.post('/admin-api/joke-call', { ...jokeForm, source: 'admin' })
    const jokeText = data.joke?.type === 'single' ? data.joke.joke : `${data.joke?.setup} - ${data.joke?.delivery}`
    jokeResult.value = { ok: true, message: 'Joke call iniciado', joke: jokeText, callId: data.call_id }
    toast.success('Joke call iniciado')
  } catch (e) {
    jokeResult.value = { ok: false, message: e.response?.data?.error || 'Falló la llamada' }
    toast.error(e)
  } finally { jokeLoading.value = false }
}

async function generateStyle() {
  if (!form.scenario.trim() || generating.value) return
  generating.value = true
  try {
    const { data } = await axios.post('/api/generate-style', { scenario: form.scenario.trim() }, { timeout: 12000 })
    const clean = String(data.style || '').replace(/```(?:json)?/g, '').trim()
    if (clean) form.character = clean
    if (data.voice) form.voice = data.voice
    if (!clean) toast.error('Auto IA no generó un estilo. Escríbelo manualmente.')
  } catch (e) {
    toast.error(e.code === 'ECONNABORTED'
      ? 'Auto IA tardó demasiado. Escribe el estilo manualmente.'
      : e)
  } finally { generating.value = false }
}

const form = reactive({ phone_number: '', victim_name: '', character: '', voice: 'ash', scenario: '' })
const loading = ref(false)
const result = ref(null)

onMounted(async () => {
  try { const { data } = await axios.get('/admin-api/presets'); presets.value = data } catch {}
})

function usePreset(p) {
  form.scenario = p.scenario
  form.character = p.style || p.character || ''
  form.voice = p.voice || 'ash'
  activePreset.value = p.id
}

async function launch() {
  loading.value = true; result.value = null
  try {
    const { data } = await axios.post('/admin-api/launch-call', form, { timeout: 45000 })
    result.value = { ok: true, message: `Llamada iniciada · SID ${data.call_sid}`, callId: data.call_id }
    toast.success('Llamada iniciada')
  } catch (e) {
    let msg = e.response?.data?.error
    if (!msg && e.response?.data?.errors) msg = Object.values(e.response.data.errors).flat().join(' · ')
    if (!msg) msg = e.response?.data?.message
    if (!msg) {
      if (e.code === 'ECONNABORTED') msg = 'La solicitud tardó demasiado. Intenta de nuevo.'
      else if (!e.response) msg = 'Sin conexión al servidor.'
      else if (e.response.status >= 500) msg = `Servidor ocupado (${e.response.status}). Reintenta.`
      else msg = `Error ${e.response.status}`
    }
    result.value = { ok: false, message: msg }
    toast.error(msg)
  } finally { loading.value = false }
}

function normalizeMxPhone(raw) {
  if (!raw) return null
  let d = String(raw).replace(/\D/g, '')
  if (d.startsWith('00')) d = d.slice(2)
  if (d.startsWith('521') && d.length === 13) d = d.slice(3)
  else if (d.startsWith('52') && d.length === 12) d = d.slice(2)
  if (d.length === 10 && /^[1-9]/.test(d)) return d
  return null
}
function formatPhone(e) { form.phone_number = e.target.value.replace(/\D/g, '').slice(0, 10) }
function onPhonePaste(e) {
  const pasted = (e.clipboardData || window.clipboardData)?.getData('text') || ''
  const normalized = normalizeMxPhone(pasted)
  if (normalized) { e.preventDefault(); form.phone_number = normalized }
}

const contactPickerSupported = typeof navigator !== 'undefined' && 'contacts' in navigator && 'ContactsManager' in window
const isIOS = typeof navigator !== 'undefined' && /iPad|iPhone|iPod/.test(navigator.userAgent)

async function pickContact() {
  if (contactPickerSupported) {
    try {
      const picked = await navigator.contacts.select(['tel'], { multiple: false })
      if (!picked || !picked.length) return
      const tels = picked[0].tel || []
      let normalized = null
      for (const t of tels) { normalized = normalizeMxPhone(t); if (normalized) break }
      if (!normalized) { toast.error('Solo se aceptan números de México (+52).'); return }
      form.phone_number = normalized
    } catch (err) { if (err?.name !== 'AbortError') toast.error('No se pudo acceder a contactos.') }
    return
  }
  toast.info(isIOS
    ? 'En iPhone: toca el campo y elige un contacto desde las sugerencias.'
    : 'Esta función sólo funciona en Chrome de Android.')
}
</script>
