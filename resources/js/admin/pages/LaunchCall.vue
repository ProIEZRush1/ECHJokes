<template>
  <div class="p-4 md:p-6 max-w-2xl">
    <h1 class="text-2xl font-bold font-mono mb-6">Launch Call</h1>

    <form @submit.prevent="launch" class="space-y-5">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-xs text-gray-400 uppercase tracking-wider mb-1.5">Phone Number</label>
          <input v-model="form.phone_number" required placeholder="+525512345678"
            class="w-full bg-matrix-800 border border-matrix-600 rounded-lg px-3 py-2.5 text-sm text-white placeholder-gray-500 focus:outline-none focus:border-neon/50 transition" />
          <p class="text-[10px] text-gray-500 mt-1">Without +52 it's added automatically</p>
        </div>
        <div>
          <label class="block text-xs text-gray-400 uppercase tracking-wider mb-1.5">Voice</label>
          <div class="grid grid-cols-2 gap-2">
            <label v-for="v in voices" :key="v.id"
              class="flex items-center gap-2 p-2.5 rounded-lg border cursor-pointer transition"
              :class="form.voice === v.id ? 'border-neon bg-neon/10 text-white' : 'border-matrix-600 text-gray-400'">
              <input type="radio" v-model="form.voice" :value="v.id" class="hidden" />
              <span class="text-lg">{{ v.emoji }}</span>
              <div><p class="text-xs font-semibold">{{ v.label }}</p></div>
            </label>
          </div>
        </div>
      </div>

      <div>
        <label class="block text-xs text-gray-400 uppercase tracking-wider mb-1.5">Prank Scenario</label>
        <textarea v-model="form.scenario" required rows="4"
          placeholder="Describe the prank scenario in detail..."
          class="w-full bg-matrix-800 border border-matrix-600 rounded-lg px-3 py-2.5 text-sm text-white placeholder-gray-500 focus:outline-none focus:border-neon/50 transition resize-none"></textarea>
      </div>

      <!-- Style -->
      <div>
        <label class="block text-xs text-gray-400 uppercase tracking-wider mb-1.5">Speaking Style <span class="text-red-400">*</span></label>
        <div class="flex gap-2">
          <input v-model="form.character" placeholder="Ej: Formal y serio, Chistoso y sarcastico, Nervioso..."
            class="flex-1 bg-matrix-800 border border-matrix-600 rounded-lg px-3 py-2.5 text-sm text-white placeholder-gray-500 focus:outline-none focus:border-neon/50 transition" />
          <button type="button" @click="generateStyle" :disabled="generating || !form.scenario.trim()"
            class="px-3 py-2.5 rounded-lg bg-matrix-700 border border-matrix-600 text-xs text-gray-400 hover:text-neon hover:border-neon/30 transition whitespace-nowrap disabled:opacity-30">
            {{ generating ? '...' : 'Auto IA' }}
          </button>
        </div>
      </div>

      <!-- Presets -->
      <div v-if="presets.length">
        <p class="text-xs text-gray-400 uppercase mb-2">Quick presets</p>
        <div class="grid grid-cols-2 lg:grid-cols-3 gap-2">
          <button v-for="p in presets" :key="p.id" type="button" @click="usePreset(p)"
            :class="['flex items-center gap-2 p-2 rounded-xl border text-left transition text-xs',
              activePreset === p.id ? 'border-neon bg-neon/10 text-white' : 'border-matrix-600 text-gray-400 hover:border-neon/30']">
            <span class="text-lg flex-shrink-0">{{ p.emoji }}</span>
            <span class="truncate">{{ p.label }}</span>
          </button>
        </div>
      </div>

      <button type="submit" :disabled="loading || !form.character.trim()"
        class="w-full py-3 rounded-xl bg-neon text-matrix-900 font-bold text-base hover:shadow-neon transition disabled:opacity-50">
        {{ loading ? 'Launching...' : 'Launch Call' }}
      </button>

      <div v-if="result" class="p-4 rounded-xl text-sm" :class="result.ok ? 'bg-green-500/10 border border-green-500/20 text-green-400' : 'bg-red-500/10 border border-red-500/20 text-red-400'">
        <p>{{ result.message }}</p>
        <router-link v-if="result.callId" :to="'/admin/calls/' + result.callId" class="inline-block mt-2 text-neon text-xs hover:underline">View call &rarr;</router-link>
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import axios from 'axios'

const presets = ref([])
const activePreset = ref(null)
const generating = ref(false)

async function generateStyle() {
  if (!form.scenario.trim() || generating.value) return
  generating.value = true
  try {
    const { data } = await axios.post('/api/generate-style', { scenario: form.scenario.trim() })
    if (data.style) form.character = data.style
  } catch {} finally { generating.value = false }
}
const voices = [
  { id: 'ash', emoji: '\uD83D\uDC68', label: 'Male' },
  { id: 'coral', emoji: '\uD83D\uDC69', label: 'Female' },
]

const form = reactive({ phone_number: '', character: '', voice: 'ash', scenario: '' })
const loading = ref(false)
const result = ref(null)

onMounted(async () => {
  try { const { data } = await axios.get('/admin-api/presets'); presets.value = data } catch {}
})

function usePreset(p) {
  form.scenario = p.scenario
  form.character = p.character || ''
  form.voice = p.voice || 'ash'
  activePreset.value = p.id
}

async function launch() {
  loading.value = true; result.value = null
  try {
    const { data } = await axios.post('/admin-api/launch-call', form)
    result.value = { ok: true, message: `Call initiated! SID: ${data.call_sid}`, callId: data.call_id }
  } catch (e) {
    result.value = { ok: false, message: e.response?.data?.error || 'Failed' }
  } finally { loading.value = false }
}
</script>
