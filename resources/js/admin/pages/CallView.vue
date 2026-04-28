<template>
  <div v-if="call" class="p-6 lg:p-8 space-y-5 max-w-[1400px]">
    <!-- Hero header -->
    <header class="flex items-center gap-4 flex-wrap">
      <UiButton variant="ghost" size="sm" @click="$router.push('/admin/calls')">
        <ArrowLeft class="w-4 h-4" /> Calls
      </UiButton>
      <div class="flex-1 min-w-0">
        <div class="text-[11.5px] uppercase tracking-wider text-gray-500 font-semibold">Call · {{ shortId }}</div>
        <h1 class="text-[24px] font-bold tracking-tight font-mono">{{ call.phone_number }}</h1>
      </div>

      <span v-if="isLive" class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full border border-red-500/40 bg-red-500/10 text-red-300 font-semibold text-sm">
        <span class="relative flex h-2 w-2">
          <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75" />
          <span class="relative inline-flex rounded-full h-2 w-2 bg-red-500" />
        </span>
        Live
      </span>

      <div v-if="canRetry" class="flex items-center gap-2">
        <UiSelect
          v-if="isJokeCall"
          v-model="retryJokeMode"
          :options="[{ value: 'new', label: 'Nuevo chiste' }, { value: 'same', label: 'Mismo chiste' }]"
          class="w-40"
        />
        <UiButton variant="primary" :loading="retrying" @click="retryCall">
          <RotateCw class="w-4 h-4" /> Retry
        </UiButton>
      </div>

      <UiButton v-if="isLive" variant="danger" :loading="hangingUp" @click="hangup">
        <PhoneOff class="w-4 h-4" /> Colgar
      </UiButton>
    </header>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
      <!-- Left: info + scenario + recording -->
      <div class="lg:col-span-2 space-y-5">
        <UiCard>
          <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
            <div>
              <p class="text-[10.5px] text-gray-500 uppercase tracking-wide">Status</p>
              <UiBadge :status="call.status" :pulse="isLive" class="mt-1" />
            </div>
            <div>
              <p class="text-[10.5px] text-gray-500 uppercase tracking-wide">Duration</p>
              <p class="font-mono text-sm mt-1">{{ formatDuration(call.call_duration_seconds) }}</p>
            </div>
            <div>
              <p class="text-[10.5px] text-gray-500 uppercase tracking-wide">Created</p>
              <p class="text-sm mt-1 text-gray-300">{{ formatDate(call.created_at) }}</p>
            </div>
            <div v-if="call.victim_name">
              <p class="text-[10.5px] text-gray-500 uppercase tracking-wide">Víctima</p>
              <p class="text-sm mt-1 text-white font-medium">{{ call.victim_name }}</p>
            </div>
            <div v-if="call.voice">
              <p class="text-[10.5px] text-gray-500 uppercase tracking-wide">Voz</p>
              <p class="text-sm mt-1 text-gray-300 font-mono">{{ call.voice }}</p>
            </div>
            <div class="col-span-2 md:col-span-3 min-w-0">
              <p class="text-[10.5px] text-gray-500 uppercase tracking-wide">Call SID</p>
              <p class="font-mono text-xs mt-1 text-gray-500 break-all">{{ call.twilio_call_sid || '—' }}</p>
            </div>
            <div v-if="call.failure_reason" class="col-span-2 md:col-span-3 bg-red-500/10 border border-red-500/30 rounded-lg p-3">
              <p class="text-[10.5px] text-red-300 uppercase tracking-wide font-semibold">Error</p>
              <p class="text-sm mt-1 text-red-200">{{ call.failure_reason }}</p>
            </div>
          </div>
        </UiCard>

        <UiCard :title="call.delivery_type === 'joke_call' ? 'Joke' : 'Scenario'">
          <p class="text-sm text-gray-200 whitespace-pre-wrap leading-relaxed">{{ call.custom_joke_prompt || call.joke_text || 'No content' }}</p>
        </UiCard>

        <UiCard v-if="call.recording_url" title="Recording">
          <audio
            controls
            :src="call.recording_url"
            preload="metadata"
            class="w-full"
            style="filter: hue-rotate(100deg) saturate(2)"
          />
        </UiCard>

        <UiCard v-if="call.session_id && call.recording_url" title="Compartir">
          <div class="flex flex-wrap gap-2 items-center mb-3">
            <input
              :value="shareUrl"
              readonly
              class="flex-1 min-w-[240px] bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-xs font-mono text-gray-300 focus:outline-none focus:border-neon/50"
            />
            <UiButton variant="primary" size="sm" @click="copyShareUrl">
              <Copy class="w-3 h-3" />
              {{ shareCopied ? 'Copiado' : 'Copiar' }}
            </UiButton>
          </div>
          <div class="grid grid-cols-3 gap-2">
            <a :href="whatsappShare" target="_blank" rel="noopener" class="text-center py-2 rounded-lg bg-[#25D366] hover:bg-[#1ebe5a] text-white text-xs font-semibold transition">WhatsApp</a>
            <a :href="twitterShare" target="_blank" rel="noopener" class="text-center py-2 rounded-lg bg-[#1da1f2] hover:bg-[#0d8fd9] text-white text-xs font-semibold transition">Twitter</a>
            <a :href="shareUrl" target="_blank" rel="noopener" class="text-center py-2 rounded-lg bg-white/5 hover:bg-white/10 border border-white/10 text-white text-xs font-semibold transition">Open</a>
          </div>
          <a :href="downloadUrl" download class="block text-center mt-3 text-xs text-gray-500 hover:text-neon transition">
            <Download class="w-3 h-3 inline-block mr-1" /> Download watermarked MP3
          </a>
        </UiCard>
      </div>

      <!-- Right: live listen + transcript -->
      <div class="space-y-4">
        <UiCard v-if="isLive && call.twilio_call_sid">
          <UiButton
            :variant="listening ? 'secondary' : 'primary'"
            class="w-full"
            @click="toggleListen"
          >
            <Headphones class="w-4 h-4" />
            {{ listening ? 'Stop listening' : 'Listen live' }}
          </UiButton>
          <p v-if="listenStatus" class="text-xs text-center mt-2" :class="listening ? 'text-neon' : 'text-gray-500'">
            {{ listenStatus }}
          </p>
        </UiCard>

        <UiCard :title="isLive ? 'Live transcript' : 'Transcript'" :padded="false">
          <div v-if="transcript.length" class="space-y-3 max-h-[560px] overflow-y-auto p-4" ref="transcriptEl">
            <div
              v-for="(line, i) in transcript"
              :key="i"
              :class="['flex gap-2 animate-[fade-in-up_0.25s_ease-out]', line.role !== 'ai' ? 'flex-row-reverse' : '']"
            >
              <Avatar
                :name="line.role === 'ai' ? 'AI' : 'Tú'"
                size="xs"
                :square="true"
              />
              <div
                :class="[
                  'max-w-[78%] rounded-xl px-3 py-2 text-[13px] leading-relaxed',
                  line.role === 'ai'
                    ? 'bg-neon/10 border border-neon/15 text-gray-100 rounded-tl-sm'
                    : 'bg-blue-500/10 border border-blue-500/15 text-gray-100 rounded-tr-sm'
                ]"
              >
                {{ line.text }}
                <span class="block text-[10px] text-gray-500 mt-0.5 font-mono">{{ line.at }}</span>
              </div>
            </div>
          </div>
          <UiEmptyState
            v-else
            :icon="MessageSquare"
            :title="isLive ? 'Esperando conversación…' : 'Sin transcript'"
          />
        </UiCard>
      </div>
    </div>
  </div>

  <div v-else class="p-8 flex items-center justify-center min-h-[50vh]">
    <UiSpinner :size="20" />
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue'
import { useRoute } from 'vue-router'
import axios from 'axios'
import {
  ArrowLeft, RotateCw, PhoneOff, Copy, Download, Headphones, MessageSquare,
} from 'lucide-vue-next'

import UiCard from '../components/UiCard.vue'
import UiButton from '../components/UiButton.vue'
import UiBadge from '../components/UiBadge.vue'
import UiSelect from '../components/UiSelect.vue'
import UiSpinner from '../components/UiSpinner.vue'
import UiEmptyState from '../components/UiEmptyState.vue'
import Avatar from '../components/Avatar.vue'
import { useToast } from '../composables/useToast.js'
import { useConfirm } from '../composables/useConfirm.js'

const route = useRoute()
const toast = useToast()
const confirm = useConfirm()

const call = ref(null)
const transcript = ref([])
const transcriptEl = ref(null)
const listening = ref(false)
const listenStatus = ref('')
const retryJokeMode = ref('new')
const retrying = ref(false)
const hangingUp = ref(false)
const shareCopied = ref(false)

let pollInterval, ws, audioCtx, gainNode, schedTime = 0

// Mulaw decode table for Listen Live
const MULAW = new Float32Array(256)
for (let i = 0; i < 256; i++) {
  let mu = ~i & 0xFF, sign = (mu & 0x80) ? -1 : 1
  mu &= 0x7F
  let e = (mu >> 4) & 7, m = mu & 0xF
  let s = (m << (e + 3)) + (1 << (e + 3)) - 132
  MULAW[i] = sign * Math.min(s, 32767) / 32768
}

const isLive = computed(() => ['calling', 'in_progress'].includes(call.value?.status))
const canRetry = computed(() => ['completed', 'failed', 'voicemail'].includes(call.value?.status))
const isJokeCall = computed(() => call.value?.delivery_type === 'joke_call')
const shortId = computed(() => (call.value?.id || '').slice(-8))

const shareUrl     = computed(() => call.value?.session_id ? `${window.location.origin}/share/${call.value.session_id}` : '')
const downloadUrl  = computed(() => call.value?.session_id ? `/share/${call.value.session_id}/audio.mp3` : '')
const shareCaption = computed(() => `Escucha esta broma telefónica que hice con IA 😂 ${shareUrl.value}`)
const whatsappShare= computed(() => `https://wa.me/?text=${encodeURIComponent(shareCaption.value)}`)
const twitterShare = computed(() => `https://twitter.com/intent/tweet?text=${encodeURIComponent(shareCaption.value)}`)

function copyShareUrl() {
  if (!navigator.clipboard || !shareUrl.value) return
  navigator.clipboard.writeText(shareUrl.value)
  shareCopied.value = true
  toast.success('Link copiado')
  setTimeout(() => { shareCopied.value = false }, 2000)
}

async function hangup() {
  if (!await confirm({ title: '¿Colgar la llamada?', body: 'La AI parará inmediatamente.', danger: true, confirmLabel: 'Colgar' })) return
  hangingUp.value = true
  try {
    await axios.post(`/admin-api/calls/${route.params.id}/hangup`)
    await fetchCall()
    toast.success('Llamada colgada')
  } catch (e) { toast.error(e) } finally { hangingUp.value = false }
}

async function retryCall() {
  if (!call.value) return
  retrying.value = true
  try {
    let data
    if (isJokeCall.value) {
      const res = await axios.post('/admin-api/joke-call', {
        phone_number: call.value.phone_number,
        language: call.value.voice || 'es',
        source: 'admin',
      })
      data = res.data
    } else {
      const res = await axios.post('/admin-api/launch-call', {
        phone_number: call.value.phone_number,
        scenario:     call.value.custom_joke_prompt || '',
        character:    call.value.character || '',
        voice:        call.value.voice || 'ash',
        victim_name:  call.value.victim_name || '',
      })
      data = res.data
    }
    window.location.href = '/admin/calls/' + data.call_id
  } catch (e) { toast.error(e) } finally { retrying.value = false }
}

async function fetchCall() {
  try {
    const { data } = await axios.get(`/admin-api/calls/${route.params.id}`)
    call.value = data
    if (data.live_transcript) {
      transcript.value = JSON.parse(data.live_transcript)
      await nextTick()
      if (transcriptEl.value) transcriptEl.value.scrollTop = transcriptEl.value.scrollHeight
    }
  } catch (e) { /* keep last */ }
}

function toggleListen() {
  if (listening.value) return stopListen()

  audioCtx = new (window.AudioContext || window.webkitAudioContext)({ sampleRate: 8000 })
  gainNode = audioCtx.createGain()
  gainNode.gain.value = 5.0
  gainNode.connect(audioCtx.destination)
  ws = new WebSocket(`wss://ws.vacilada.com/listen/${call.value.twilio_call_sid}`)
  schedTime = 0
  listenStatus.value = 'Connecting…'

  ws.onopen = () => {
    listening.value = true
    listenStatus.value = 'Connected — listening…'
  }
  ws.onmessage = (ev) => {
    try {
      const msg = JSON.parse(ev.data)
      if (msg.type === 'audio' && msg.audio && audioCtx) {
        const bin = atob(msg.audio)
        const bytes = new Uint8Array(bin.length)
        for (let i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i)
        const pcm = new Float32Array(bytes.length)
        for (let i = 0; i < bytes.length; i++) pcm[i] = MULAW[bytes[i]]
        const buf = audioCtx.createBuffer(1, pcm.length, 8000)
        buf.getChannelData(0).set(pcm)
        const src = audioCtx.createBufferSource()
        src.buffer = buf
        src.connect(gainNode)
        const now = audioCtx.currentTime
        if (schedTime < now) schedTime = now
        src.start(schedTime)
        schedTime += buf.duration
      } else if (msg.type === 'event' && msg.event === 'call_ended') {
        listenStatus.value = 'Call ended'
        stopListen()
      }
    } catch {}
  }
  ws.onerror = () => { listenStatus.value = 'Connection error'; stopListen() }
  ws.onclose = () => { if (listening.value) stopListen() }
}

function stopListen() {
  listening.value = false
  try { ws?.close() } catch {} ws = null
  try { audioCtx?.close() } catch {} audioCtx = null
}

function formatDuration(s) { return s ? `${String(Math.floor(s/60)).padStart(2,'0')}:${String(s%60).padStart(2,'0')}` : '—' }
function formatDate(d) { return d ? new Date(d).toLocaleString('es-MX') : '' }

onMounted(() => {
  fetchCall()
  pollInterval = setInterval(fetchCall, 3000)
})
onUnmounted(() => {
  clearInterval(pollInterval)
  stopListen()
})
</script>
