<template>
  <div class="p-6 space-y-6" v-if="call">
    <!-- Header -->
    <div class="flex items-center gap-4">
      <router-link to="/admin/calls" class="text-gray-400 hover:text-white transition">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
      </router-link>
      <h1 class="text-2xl font-bold font-mono">Call Details</h1>

      <!-- Retry button -->
      <button v-if="canRetry" @click="retryCall" :disabled="retrying"
        class="ml-auto px-4 py-1.5 rounded-lg bg-neon text-matrix-900 font-bold text-sm hover:shadow-neon transition disabled:opacity-50">
        {{ retrying ? 'Retrying...' : 'Retry Call' }}
      </button>

      <span v-if="isLive" class="flex items-center gap-2 text-sm">
        <span class="relative flex h-2.5 w-2.5">
          <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
          <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-red-500"></span>
        </span>
        <span class="text-red-400 font-semibold uppercase text-xs tracking-wider">Live</span>
      </span>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Left: Info -->
      <div class="lg:col-span-2 space-y-6">
        <!-- Call Info Card -->
        <div class="bg-matrix-800 border border-matrix-600 rounded-xl p-5">
          <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
            <div>
              <p class="text-xs text-gray-500 uppercase">Phone</p>
              <p class="font-mono text-sm mt-1">{{ call.phone_number }}</p>
            </div>
            <div>
              <p class="text-xs text-gray-500 uppercase">Status</p>
              <span class="inline-block mt-1 px-2 py-0.5 rounded-full text-xs font-medium" :class="statusClass(call.status)">
                {{ call.status }}
              </span>
            </div>
            <div>
              <p class="text-xs text-gray-500 uppercase">Duration</p>
              <p class="font-mono text-sm mt-1">{{ formatDuration(call.call_duration_seconds) }}</p>
            </div>
            <div>
              <p class="text-xs text-gray-500 uppercase">Call SID</p>
              <p class="font-mono text-xs mt-1 text-gray-400 break-all">{{ call.twilio_call_sid || '-' }}</p>
            </div>
            <div>
              <p class="text-xs text-gray-500 uppercase">Created</p>
              <p class="text-sm mt-1 text-gray-300">{{ formatDate(call.created_at) }}</p>
            </div>
            <div v-if="call.failure_reason">
              <p class="text-xs text-gray-500 uppercase">Error</p>
              <p class="text-sm mt-1 text-red-400">{{ call.failure_reason }}</p>
            </div>
          </div>
        </div>

        <!-- Scenario -->
        <div class="bg-matrix-800 border border-matrix-600 rounded-xl p-5">
          <h2 class="text-sm font-semibold text-gray-400 uppercase mb-2">Scenario</h2>
          <p class="text-sm text-gray-200 whitespace-pre-wrap leading-relaxed">{{ call.custom_joke_prompt || 'No scenario' }}</p>
        </div>

        <!-- Recording -->
        <div v-if="call.recording_url" class="bg-matrix-800 border border-matrix-600 rounded-xl p-5">
          <h2 class="text-sm font-semibold text-gray-400 uppercase mb-3">Recording</h2>
          <audio controls class="w-full" :src="call.recording_url" preload="metadata"
            style="filter: hue-rotate(100deg) saturate(2);"></audio>
        </div>
      </div>

      <!-- Right: Transcript + Listen -->
      <div class="space-y-4">
        <!-- Listen Live -->
        <div v-if="isLive && call.twilio_call_sid" class="bg-matrix-800 border border-neon/20 rounded-xl p-4">
          <button @click="toggleListen" class="w-full py-3 rounded-lg font-bold text-sm transition"
            :class="listening ? 'bg-gray-600 text-white' : 'bg-neon text-matrix-900 hover:shadow-neon'">
            {{ listening ? 'Stop Listening' : 'Listen Live' }}
          </button>
          <p v-if="listenStatus" class="text-xs text-center mt-2" :class="listening ? 'text-neon' : 'text-gray-500'">
            {{ listenStatus }}
          </p>
        </div>

        <!-- Transcript -->
        <div class="bg-matrix-800 border border-matrix-600 rounded-xl p-4">
          <h2 class="text-sm font-semibold text-gray-400 uppercase mb-3">
            {{ isLive ? 'Live Transcript' : 'Transcript' }}
          </h2>
          <div v-if="transcript.length" class="space-y-3 max-h-[500px] overflow-y-auto" ref="transcriptEl">
            <div v-for="(line, i) in transcript" :key="i"
              class="flex gap-2" :class="line.role !== 'ai' ? 'flex-row-reverse' : ''">
              <div class="flex-shrink-0 w-7 h-7 rounded-full flex items-center justify-center text-[10px] font-bold"
                :class="line.role === 'ai' ? 'bg-neon/20 text-neon' : 'bg-blue-500/20 text-blue-400'">
                {{ line.role === 'ai' ? 'AI' : 'H' }}
              </div>
              <div class="max-w-[85%] rounded-xl px-3 py-2 text-xs leading-relaxed"
                :class="line.role === 'ai'
                  ? 'bg-neon/10 text-gray-200 rounded-tl-sm'
                  : 'bg-blue-500/10 text-gray-200 rounded-tr-sm'">
                {{ line.text }}
                <span class="block text-[9px] opacity-30 mt-0.5">{{ line.at }}</span>
              </div>
            </div>
          </div>
          <p v-else class="text-center text-gray-500 text-sm py-6">
            {{ isLive ? 'Waiting for conversation...' : 'No transcript available' }}
          </p>
        </div>
      </div>
    </div>
  </div>

  <div v-else class="p-6 flex items-center justify-center min-h-[50vh]">
    <div class="text-gray-500">Loading...</div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue'
import { useRoute } from 'vue-router'
import axios from 'axios'

const route = useRoute()
const call = ref(null)
const transcript = ref([])
const transcriptEl = ref(null)
const listening = ref(false)
const listenStatus = ref('')
let pollInterval, ws, audioCtx, gainNode, schedTime = 0

// Mulaw table
const MULAW = new Float32Array(256)
for (let i = 0; i < 256; i++) {
  let mu = ~i & 0xFF, sign = (mu & 0x80) ? -1 : 1
  mu &= 0x7F
  let e = (mu >> 4) & 7, m = mu & 0xF
  let s = (m << (e + 3)) + (1 << (e + 3)) - 132
  MULAW[i] = sign * Math.min(s, 32767) / 32768
}

const isLive = computed(() => {
  const s = call.value?.status
  return s === 'calling' || s === 'in_progress'
})

const canRetry = computed(() => {
  const s = call.value?.status
  return s === 'completed' || s === 'failed' || s === 'voicemail'
})

const retrying = ref(false)

async function retryCall() {
  if (!call.value) return
  retrying.value = true
  try {
    const { data } = await axios.post('/admin-api/launch-call', {
      phone_number: call.value.phone_number,
      scenario: call.value.custom_joke_prompt || '',
      character: '',
      voice: call.value.voice || 'ash',
    })
    // Navigate to the new call
    window.location.href = '/admin/calls/' + data.call_id
  } catch (e) {
    alert(e.response?.data?.error || 'Retry failed')
  } finally { retrying.value = false }
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
  } catch {}
}

function toggleListen() {
  if (listening.value) return stopListen()

  audioCtx = new (window.AudioContext || window.webkitAudioContext)({ sampleRate: 8000 })
  // Amplify audio 5x (mulaw phone audio is very quiet)
  gainNode = audioCtx.createGain()
  gainNode.gain.value = 5.0
  gainNode.connect(audioCtx.destination)
  ws = new WebSocket(`wss://ws.echjokes.overcloud.us:8443/listen/${call.value.twilio_call_sid}`)
  schedTime = 0
  listenStatus.value = 'Connecting...'

  ws.onopen = () => {
    listening.value = true
    listenStatus.value = 'Connected - listening...'
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

function statusClass(status) {
  const map = { completed: 'bg-green-500/20 text-green-400', failed: 'bg-red-500/20 text-red-400', voicemail: 'bg-yellow-500/20 text-yellow-400', calling: 'bg-blue-500/20 text-blue-400', in_progress: 'bg-purple-500/20 text-purple-400' }
  return map[status] || 'bg-gray-500/20 text-gray-400'
}

function formatDuration(s) { return s ? `${String(Math.floor(s/60)).padStart(2,'0')}:${String(s%60).padStart(2,'0')}` : '-' }
function formatDate(d) { return d ? new Date(d).toLocaleString() : '' }

onMounted(() => {
  fetchCall()
  pollInterval = setInterval(fetchCall, 3000)
})

onUnmounted(() => {
  clearInterval(pollInterval)
  stopListen()
})
</script>
