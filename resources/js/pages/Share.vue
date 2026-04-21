<template>
  <div class="min-h-screen flex flex-col">
    <nav class="px-4 py-4 border-b border-matrix-700">
      <div class="max-w-2xl mx-auto flex justify-between items-center">
        <router-link to="/" class="text-lg font-bold font-mono text-neon">Vacilada</router-link>
        <a :href="signupUrl" class="text-sm bg-neon text-matrix-900 font-bold px-3 py-1.5 rounded-lg">Hacer broma</a>
      </div>
    </nav>

    <main class="max-w-xl mx-auto w-full px-4 py-6 flex-1">
      <div class="text-center mb-4">
        <div class="text-5xl mb-3">&#x1F3AD;</div>
        <h1 class="text-2xl md:text-3xl font-bold text-white leading-tight">
          <template v-if="creatorName && victimName">
            <span class="text-neon">{{ creatorName }}</span> le hizo una vacilada a <span class="text-neon">{{ victimName }}</span>
          </template>
          <template v-else-if="victimName">
            Le hicieron una vacilada a <span class="text-neon">{{ victimName }}</span>
          </template>
          <template v-else>
            Vacilada telefónica
          </template>
        </h1>
      </div>

      <div v-if="scenario" class="bg-matrix-800 border border-matrix-600 rounded-xl p-4 mb-4">
        <div class="text-xs text-gray-500 uppercase mb-1">Escenario</div>
        <p class="text-sm text-gray-200">{{ scenario }}</p>
      </div>

      <div v-if="recordingUrl" class="bg-matrix-800 border border-matrix-600 rounded-xl p-5 mb-4">
        <audio :src="recordingUrl" controls class="w-full rounded-lg" preload="metadata" style="filter: hue-rotate(90deg) saturate(1.5);"></audio>
        <div class="flex items-center justify-between mt-3 text-xs text-gray-500">
          <span>&#x1F441;&#xFE0F; {{ shareViews }} {{ shareViews === 1 ? 'escucha' : 'escuchas' }}</span>
          <span v-if="durationSeconds">{{ formatDuration(durationSeconds) }}</span>
        </div>
      </div>

      <div v-if="recordingUrl" class="space-y-2 mb-6">
        <WhatsAppShareButton :url="shareUrl" :victim-name="victimName" :call-id="sessionSlug" />
        <button @click="copyLink" class="w-full flex items-center justify-center gap-2 bg-matrix-700 hover:bg-matrix-600 text-white rounded-xl py-3 px-4 font-semibold text-sm transition border border-matrix-600">
          {{ copied ? '✓ Copiado!' : '📋 Copiar link' }}
        </button>
      </div>

      <a :href="signupUrl" class="block w-full bg-neon text-matrix-900 font-bold text-base md:text-lg py-4 rounded-xl hover:shadow-[var(--shadow-neon-lg)] transition text-center mb-3">
        &#x1F4DE; Haz la tuya GRATIS
      </a>
      <p class="text-xs text-gray-500 text-center mb-8">Te regalamos 2 bromas al registrarte. Sin tarjeta.</p>

      <div v-if="transcript.length" class="bg-matrix-800 border border-matrix-600 rounded-xl p-4 mb-6">
        <button @click="showTranscript = !showTranscript" class="w-full flex justify-between items-center text-left text-sm font-semibold text-gray-400 uppercase">
          Transcripción
          <span>{{ showTranscript ? '▲' : '▼' }}</span>
        </button>
        <div v-if="showTranscript" class="mt-3 space-y-2 max-h-96 overflow-y-auto">
          <div v-for="(msg, i) in transcript" :key="i" :class="msg.role === 'ai' ? 'text-neon' : 'text-gray-300'" class="text-sm">
            <strong class="opacity-60">{{ msg.role === 'ai' ? 'IA:' : 'Persona:' }}</strong> {{ msg.text }}
          </div>
        </div>
      </div>
    </main>

    <footer class="border-t border-matrix-700 py-6 text-center text-xs text-gray-500">
      Vacilada &middot; Hecho en México 🇲🇽
    </footer>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import axios from 'axios'
import WhatsAppShareButton from '../components/WhatsAppShareButton.vue'

const route = useRoute()
const scenario = ref('')
const recordingUrl = ref('')
const victimName = ref('')
const creatorName = ref('')
const transcript = ref([])
const durationSeconds = ref(0)
const shareViews = ref(0)
const sessionSlug = ref('')
const showTranscript = ref(false)
const copied = ref(false)

const shareUrl = computed(() => typeof window !== 'undefined' ? window.location.href.split('?')[0] : '')
const signupUrl = computed(() => `/login?ref=${sessionSlug.value || ''}&utm_source=share&utm_medium=organic&utm_campaign=${sessionSlug.value || 'share'}`)

function copyLink() {
  if (!navigator.clipboard) return
  navigator.clipboard.writeText(shareUrl.value)
  copied.value = true
  setTimeout(() => { copied.value = false }, 2000)
}

function formatDuration(sec) {
  const m = Math.floor(sec / 60); const s = sec % 60
  return `${m}:${s.toString().padStart(2, '0')}`
}

onMounted(async () => {
  const injected = window.__VACILADA__?.shareData
  const loadFromData = (d) => {
    scenario.value = d.scenario || d.joke_text || ''
    recordingUrl.value = d.recording_url || ''
    victimName.value = d.victim_name || ''
    creatorName.value = d.creator_name || ''
    shareViews.value = d.share_views || 0
    sessionSlug.value = d.slug || route.params.slug || route.params.sessionId || ''
    durationSeconds.value = d.call_duration_seconds || 0
    transcript.value = d.transcript || d.conversation || []
  }
  if (injected) { loadFromData(injected); return }
  try {
    const slug = route.params.slug || route.params.sessionId
    const endpoint = route.params.slug ? `/v/${slug}` : `/share/${slug}`
    const { data } = await axios.get(endpoint, { headers: { Accept: 'application/json' } })
    loadFromData(data)
  } catch {}
})
</script>
