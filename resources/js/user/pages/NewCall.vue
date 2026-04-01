<template>
  <div class="max-w-lg mx-auto space-y-6">
    <h1 class="text-2xl font-bold font-mono">Nueva Broma</h1>

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
        <label class="block text-xs text-gray-400 uppercase mb-1.5">Voz</label>
        <div class="grid grid-cols-2 gap-2">
          <button type="button" @click="voice = 'ash'"
            :class="['flex items-center gap-2 p-2.5 rounded-xl border transition',
              voice === 'ash' ? 'border-neon bg-neon/10' : 'border-matrix-600 text-gray-400']">
            <span>&#x1F468;</span><span class="text-sm">Hombre</span>
          </button>
          <button type="button" @click="voice = 'coral'"
            :class="['flex items-center gap-2 p-2.5 rounded-xl border transition',
              voice === 'coral' ? 'border-neon bg-neon/10' : 'border-matrix-600 text-gray-400']">
            <span>&#x1F469;</span><span class="text-sm">Mujer</span>
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
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'

const router = useRouter()
const phone = ref('')
const voice = ref('ash')
const scenario = ref('')
const style = ref('')
const generating = ref(false)
const credits = ref(null)
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
    presets.value = pr.data
  } catch {}
})

async function generateStyle() {
  if (!scenario.value.trim() || generating.value) return
  generating.value = true
  try {
    const { data } = await axios.post('/api/generate-style', { scenario: scenario.value.trim() })
    if (data.style) style.value = data.style
  } catch {} finally { generating.value = false }
}

function usePreset(p) {
  scenario.value = p.scenario
  if (p.voice) voice.value = p.voice
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
    })
    router.push(data.redirect)
  } catch (e) {
    error.value = e.response?.data?.error || 'Error'
    if (e.response?.data?.show_plans) router.push('/pricing')
  } finally { loading.value = false }
}
</script>
