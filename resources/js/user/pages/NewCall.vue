<template>
  <div class="max-w-lg mx-auto space-y-6">
    <h1 class="text-2xl font-bold font-mono">Nueva Llamada</h1>

    <!-- Call Type Toggle -->
    <div class="flex gap-2">
      <button type="button" @click="callMode = 'prank'"
        :class="['px-4 py-2 rounded-lg text-sm font-bold transition', callMode === 'prank' ? 'bg-neon text-matrix-900' : 'bg-matrix-700 text-gray-400']">
        Broma con IA
      </button>
      <button type="button" @click="callMode = 'joke'"
        :class="['px-4 py-2 rounded-lg text-sm font-bold transition', callMode === 'joke' ? 'bg-neon text-matrix-900' : 'bg-matrix-700 text-gray-400']">
        Chiste rapido
      </button>
    </div>

    <!-- JOKE MODE -->
    <template v-if="callMode === 'joke'">
      <div v-if="jokesRemaining !== null" class="flex items-center gap-2 p-3 rounded-xl bg-matrix-800 border border-matrix-600 mb-4">
        <span class="text-neon font-bold font-mono text-lg">{{ jokesRemaining }}</span>
        <span class="text-sm text-gray-400">chistes este mes</span>
        <span v-if="jokesResetAt" class="text-[10px] text-gray-500 ml-auto">
          reset {{ new Date(jokesResetAt).toLocaleDateString() }}
        </span>
        <router-link v-if="jokesRemaining === 0" to="/pricing" class="text-neon text-sm hover:underline">Más</router-link>
      </div>
      <form @submit.prevent="launchJoke" class="space-y-4">
        <div>
          <label class="block text-xs text-gray-400 uppercase mb-1.5">Numero</label>
          <div class="flex items-center bg-matrix-800 border border-matrix-600 rounded-xl overflow-hidden focus-within:border-neon/50">
            <span class="px-3 py-2.5 text-gray-400 font-mono border-r border-matrix-600 text-sm">+52</span>
            <input v-model="jokePhone" type="tel" maxlength="10" placeholder="55 1234 5678"
              class="flex-1 bg-transparent px-3 py-2.5 text-white font-mono outline-none placeholder:text-gray-600"
              @input="jokePhone = $event.target.value.replace(/\D/g, '').slice(0, 10)" />
          </div>
        </div>
        <div>
          <label class="block text-xs text-gray-400 uppercase mb-1.5">Idioma</label>
          <div class="grid grid-cols-5 gap-1.5">
            <button v-for="l in jokeLangs" :key="l.id" type="button" @click="jokeLang = l.id"
              :class="['flex flex-col items-center p-2 rounded-xl border transition text-xs',
                jokeLang === l.id ? 'border-neon bg-neon/10 text-white' : 'border-matrix-600 text-gray-500']">
              <span class="text-lg">{{ l.flag }}</span><span>{{ l.label }}</span>
            </button>
          </div>
        </div>
        <button type="submit" :disabled="jokeLoading || jokePhone.length < 10"
          class="w-full py-3 rounded-xl bg-neon text-matrix-900 font-bold hover:shadow-neon transition disabled:opacity-50">
          {{ jokeLoading ? 'Llamando...' : 'Enviar Chiste' }}
        </button>
        <p v-if="jokeError" class="text-red-400 text-sm text-center">{{ jokeError }}</p>
        <div v-if="jokeOk" class="p-3 rounded-xl bg-green-500/10 border border-green-500/20 text-center">
          <p class="text-green-400 text-sm">Chiste enviado!</p>
          <p class="text-xs text-gray-400 mt-1 italic">"{{ jokeOk }}"</p>
        </div>
      </form>
    </template>

    <!-- PRANK MODE -->
    <template v-else>
    <div v-if="credits !== null" class="flex items-center gap-2 p-3 rounded-xl bg-matrix-800 border border-matrix-600">
      <span class="text-neon font-bold font-mono text-lg">{{ credits }}</span>
      <span class="text-sm text-gray-400">creditos disponibles</span>
      <router-link v-if="credits === 0" to="/pricing" class="ml-auto text-neon text-sm hover:underline">Comprar</router-link>
    </div>

    <form @submit.prevent="launch" class="space-y-4">
      <div>
        <label class="block text-xs text-gray-400 uppercase mb-1.5">Numero</label>
        <div class="flex items-center bg-matrix-800 border border-matrix-600 rounded-xl overflow-hidden focus-within:border-neon/50">
          <span class="px-3 py-2.5 text-gray-400 font-mono border-r border-matrix-600 text-sm">+52</span>
          <input v-model="phone" type="tel" maxlength="10" placeholder="55 1234 5678"
            class="flex-1 bg-transparent px-3 py-2.5 text-white font-mono outline-none placeholder:text-gray-600"
            @input="phone = $event.target.value.replace(/\D/g, '').slice(0, 10)" />
        </div>
      </div>

      <div>
        <label class="block text-xs text-gray-400 uppercase mb-1.5">Nombre de quien recibe</label>
        <input v-model="victimName" placeholder="Ej: Juan, Maria... (opcional)"
          class="w-full bg-matrix-800 border border-matrix-600 rounded-xl px-3 py-2.5 text-white text-sm outline-none focus:border-neon/50 placeholder:text-gray-600" />
        <p class="text-[10px] text-gray-500 mt-1">La broma sera mucho mas realista con el nombre</p>
      </div>

      <div>
        <label class="block text-xs text-gray-400 uppercase mb-1.5">Voz</label>
        <div class="grid grid-cols-4 gap-1.5">
          <button v-for="v in voiceOptions" :key="v.id" type="button" @click="voice = v.id"
            :class="['flex flex-col items-center p-2 rounded-xl border transition text-[11px]',
              voice === v.id ? 'border-neon bg-neon/10 text-white' : 'border-matrix-600 text-gray-500']">
            <span class="text-base mb-0.5">{{ v.emoji }}</span>
            <span class="font-medium">{{ v.label }}</span>
          </button>
        </div>
      </div>

      <div>
        <label class="block text-xs text-gray-400 uppercase mb-1.5">Escenario</label>
        <textarea v-model="scenario" rows="3" maxlength="500" required
          placeholder="Describe la broma..."
          class="w-full bg-matrix-800 border border-matrix-600 rounded-xl px-3 py-2.5 text-white text-sm outline-none focus:border-neon/50 resize-none placeholder:text-gray-600"></textarea>
      </div>

      <div>
        <label class="block text-xs text-gray-400 uppercase mb-1.5">Estilo de voz <span class="text-red-400">*</span></label>
        <div class="flex gap-2">
          <input v-model="style" placeholder="Ej: Formal y serio, Chistoso, Nervioso..."
            class="flex-1 bg-matrix-800 border border-matrix-600 rounded-xl px-3 py-2.5 text-white text-sm outline-none focus:border-neon/50 placeholder:text-gray-600" />
          <button type="button" @click="generateStyle" :disabled="generating || !scenario.trim()"
            class="px-3 py-2.5 rounded-xl bg-matrix-700 border border-matrix-600 text-xs text-gray-400 hover:text-neon hover:border-neon/30 transition whitespace-nowrap disabled:opacity-30">
            {{ generating ? '...' : 'Auto IA' }}
          </button>
        </div>
      </div>

      <!-- Presets -->
      <div v-if="presets.length">
        <p class="text-xs text-gray-500 mb-2">O elige una idea:</p>
        <div class="grid grid-cols-2 gap-2">
          <button v-for="p in presets" :key="p.id" type="button" @click="usePreset(p)"
            :class="['flex items-center gap-2 p-2 rounded-xl border text-left transition text-xs',
              activePreset === p.id ? 'border-neon bg-neon/10 text-white' : 'border-matrix-600 text-gray-400 hover:border-neon/30']">
            <span class="text-lg flex-shrink-0">{{ p.emoji }}</span>
            <span class="truncate">{{ p.label }}</span>
          </button>
        </div>
      </div>

      <button type="submit" :disabled="loading || credits === 0 || phone.length < 10 || !scenario.trim() || !style.trim()"
        class="w-full py-3 rounded-xl bg-neon text-matrix-900 font-bold hover:shadow-neon transition disabled:opacity-50">
        {{ loading ? 'Iniciando...' : 'Hacer Llamada' }}
      </button>

      <p v-if="error" class="text-red-400 text-sm text-center">{{ error }}</p>
    </form>
    </template>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'

const router = useRouter()
const callMode = ref('prank')
const jokeLangs = [
  { id: 'es', flag: '\uD83C\uDDF2\uD83C\uDDFD', label: 'ES' },
  { id: 'en', flag: '\uD83C\uDDFA\uD83C\uDDF8', label: 'EN' },
  { id: 'pt', flag: '\uD83C\uDDE7\uD83C\uDDF7', label: 'PT' },
  { id: 'fr', flag: '\uD83C\uDDEB\uD83C\uDDF7', label: 'FR' },
  { id: 'de', flag: '\uD83C\uDDE9\uD83C\uDDEA', label: 'DE' },
]
const jokePhone = ref('')
const jokeLang = ref('es')
const jokeLoading = ref(false)
const jokeError = ref('')
const jokeOk = ref('')

async function launchJoke() {
  jokeError.value = ''; jokeOk.value = ''
  jokeLoading.value = true
  try {
    const { data } = await axios.post('/user-api/joke-call', { phone_number: jokePhone.value, language: jokeLang.value, source: 'paid' })
    const j = data.joke
    jokeOk.value = j?.type === 'single' ? j.joke : `${j?.setup} ... ${j?.delivery}`
  } catch (e) { jokeError.value = e.response?.data?.error || 'Error' }
  finally { jokeLoading.value = false }
}

const voiceOptions = [
  { id: 'ash', emoji: '\uD83D\uDC68', label: 'Casual' },
  { id: 'ballad', emoji: '\uD83D\uDC54', label: 'Serio' },
  { id: 'verse', emoji: '\uD83D\uDC64', label: 'Neutro' },
  { id: 'echo', emoji: '\uD83E\uDDD2', label: 'Joven' },
  { id: 'coral', emoji: '\uD83D\uDC69', label: 'Amable' },
  { id: 'sage', emoji: '\uD83D\uDC69\u200D\uD83D\uDCBC', label: 'Pro' },
  { id: 'shimmer', emoji: '\uD83D\uDC83', label: 'Alegre' },
]
const phone = ref('')
const victimName = ref('')
const voice = ref('ash')
const scenario = ref('')
const style = ref('')
const generating = ref(false)
const credits = ref(null)
const jokesRemaining = ref(null)
const jokesResetAt = ref(null)
const loading = ref(false)
const error = ref('')
const presets = ref([])
const activePreset = ref(null)

onMounted(async () => {
  try {
    const [me, pr] = await Promise.all([
      axios.get('/user-api/me'),
      axios.get('/api/presets'),
    ])
    credits.value = me.data.user.credits
    jokesRemaining.value = me.data.user.jokes_remaining ?? 0
    jokesResetAt.value = me.data.user.jokes_reset_at || null
    presets.value = pr.data
  } catch {}
})

async function generateStyle() {
  if (!scenario.value.trim() || generating.value) return
  generating.value = true
  try {
    const { data } = await axios.post('/api/generate-style', { scenario: scenario.value.trim() })
    if (data.style) style.value = data.style
    if (data.voice) voice.value = data.voice
  } catch {} finally { generating.value = false }
}

function usePreset(p) {
  scenario.value = p.scenario
  if (p.voice) voice.value = p.voice
  if (p.style) style.value = p.style
  activePreset.value = p.id
}

async function launch() {
  error.value = ''
  loading.value = true
  try {
    const { data } = await axios.post('/user-api/make-call', {
      phone_number: phone.value,
      scenario: scenario.value,
      character: style.value,
      voice: voice.value,
      victim_name: victimName.value.trim(),
    })
    router.push(data.redirect)
  } catch (e) {
    error.value = e.response?.data?.error || 'Error'
    if (e.response?.data?.show_plans) router.push('/pricing')
  } finally { loading.value = false }
}
</script>
