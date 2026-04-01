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
        <textarea v-model="scenario" rows="4" maxlength="500" required
          placeholder="Describe la broma..."
          class="w-full bg-matrix-800 border border-matrix-600 rounded-xl px-3 py-2.5 text-white text-sm outline-none focus:border-neon/50 resize-none placeholder:text-gray-600"></textarea>
      </div>

      <button type="submit" :disabled="loading || credits === 0"
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
const credits = ref(null)
const loading = ref(false)
const error = ref('')

onMounted(async () => {
  try {
    const { data } = await axios.get('/user-api/me')
    credits.value = data.user.credits
  } catch {}
})

async function launch() {
  error.value = ''
  loading.value = true
  try {
    const { data } = await axios.post('/user-api/make-call', {
      phone_number: phone.value,
      scenario: scenario.value,
      voice: voice.value,
    })
    router.push(data.redirect)
  } catch (e) {
    error.value = e.response?.data?.error || 'Error'
    if (e.response?.data?.show_plans) router.push('/pricing')
  } finally { loading.value = false }
}
</script>
