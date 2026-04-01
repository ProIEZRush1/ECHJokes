<template>
  <div class="p-6 max-w-2xl">
    <h1 class="text-2xl font-bold font-mono mb-6">Launch Call</h1>

    <form @submit.prevent="launch" class="space-y-5">
      <div class="grid grid-cols-2 gap-4">
        <!-- Phone -->
        <div>
          <label class="block text-xs text-gray-400 uppercase tracking-wider mb-1.5">Phone Number</label>
          <input v-model="form.phone_number" required placeholder="+525512345678"
            class="w-full bg-matrix-800 border border-matrix-600 rounded-lg px-3 py-2.5 text-sm text-white
                   placeholder-gray-500 focus:outline-none focus:border-neon/50 transition" />
          <p class="text-[10px] text-gray-500 mt-1">Without +52 it's added automatically</p>
        </div>

        <!-- Voice -->
        <div>
          <label class="block text-xs text-gray-400 uppercase tracking-wider mb-1.5">Voice</label>
          <div class="grid grid-cols-2 gap-2">
            <label v-for="v in voices" :key="v.id"
              class="flex items-center gap-2 p-2.5 rounded-lg border cursor-pointer transition"
              :class="form.voice === v.id
                ? 'border-neon bg-neon/10 text-white'
                : 'border-matrix-600 text-gray-400 hover:border-gray-500'">
              <input type="radio" v-model="form.voice" :value="v.id" class="hidden" />
              <span class="text-lg">{{ v.emoji }}</span>
              <div>
                <p class="text-xs font-semibold">{{ v.label }}</p>
                <p class="text-[10px] opacity-60">{{ v.desc }}</p>
              </div>
            </label>
          </div>
        </div>
      </div>

      <!-- Character -->
      <div>
        <label class="block text-xs text-gray-400 uppercase tracking-wider mb-1.5">Character</label>
        <input v-model="form.character" required placeholder="administrador del condominio"
          class="w-full bg-matrix-800 border border-matrix-600 rounded-lg px-3 py-2.5 text-sm text-white
                 placeholder-gray-500 focus:outline-none focus:border-neon/50 transition" />
      </div>

      <!-- Scenario -->
      <div>
        <label class="block text-xs text-gray-400 uppercase tracking-wider mb-1.5">Prank Scenario</label>
        <textarea v-model="form.scenario" required rows="5"
          placeholder="La lavadora hace mucho ruido y los vecinos se quejan..."
          class="w-full bg-matrix-800 border border-matrix-600 rounded-lg px-3 py-2.5 text-sm text-white
                 placeholder-gray-500 focus:outline-none focus:border-neon/50 transition resize-none"></textarea>
      </div>

      <!-- Submit -->
      <button type="submit" :disabled="loading"
        class="w-full py-3 rounded-xl bg-neon text-matrix-900 font-bold text-base
               hover:shadow-neon transition disabled:opacity-50">
        {{ loading ? 'Launching...' : 'Launch Call' }}
      </button>

      <!-- Result -->
      <div v-if="result" class="p-4 rounded-xl text-sm" :class="result.ok ? 'bg-green-500/10 border border-green-500/20 text-green-400' : 'bg-red-500/10 border border-red-500/20 text-red-400'">
        <p>{{ result.message }}</p>
        <router-link v-if="result.callId" :to="'/panel/calls/' + result.callId"
          class="inline-block mt-2 text-neon text-xs hover:underline">
          View call &rarr;
        </router-link>
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import axios from 'axios'

const voices = [
  { id: 'ash', emoji: '👨', label: 'Male', desc: 'Ash' },
  { id: 'coral', emoji: '👩', label: 'Female', desc: 'Coral' },
]

const form = reactive({
  phone_number: '',
  character: 'administrador del condominio',
  voice: 'ash',
  scenario: '',
})

const loading = ref(false)
const result = ref(null)

async function launch() {
  loading.value = true
  result.value = null
  try {
    const { data } = await axios.post('/admin-api/launch-call', form)
    result.value = { ok: true, message: `Call initiated! SID: ${data.call_sid}`, callId: data.call_id }
  } catch (e) {
    result.value = { ok: false, message: e.response?.data?.error || 'Failed to launch call' }
  } finally {
    loading.value = false
  }
}
</script>
