<template>
  <div v-if="call" class="p-6 lg:p-8 space-y-5 max-w-[1400px]">
    <!-- Header -->
    <header class="flex items-center gap-4 flex-wrap">
      <UiButton variant="ghost" size="sm" @click="$router.push('/admin/assistant')">
        <ArrowLeft class="w-4 h-4" /> Asistente
      </UiButton>
      <div class="flex-1 min-w-0">
        <div class="text-[11.5px] uppercase tracking-wider text-gray-500 font-semibold">
          Asistente IA · {{ call.assistant_company || call.phone_number }}
        </div>
        <h1 class="text-[22px] font-bold tracking-tight truncate">{{ call.assistant_objective || call.custom_joke_prompt || 'Llamada de asistente' }}</h1>
      </div>

      <span v-if="isLive" class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full border border-red-500/40 bg-red-500/10 text-red-300 font-semibold text-sm">
        <span class="relative flex h-2 w-2">
          <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75" />
          <span class="relative inline-flex rounded-full h-2 w-2 bg-red-500" />
        </span>
        En vivo
      </span>
      <UiBadge v-else :status="call.status" />

      <UiButton v-if="!isLive" variant="secondary" @click="editAndRelaunch">
        <Pencil class="w-4 h-4" /> Editar y llamar
      </UiButton>
      <UiButton v-if="!isLive" variant="primary" :loading="retrying" @click="retry">
        <RotateCw class="w-4 h-4" /> Reintentar
      </UiButton>
      <UiButton v-if="isLive" variant="danger" :loading="hangingUp" @click="hangup">
        <PhoneOff class="w-4 h-4" /> Colgar
      </UiButton>
    </header>

    <!-- WHY IT FAILED -->
    <div v-if="!isLive && call.failure_reason"
      class="rounded-2xl border border-red-500/40 bg-red-500/10 p-4">
      <div class="flex items-center gap-2 text-red-300 font-bold text-sm uppercase tracking-wide mb-1">
        <AlertTriangle class="w-4 h-4" /> Por qué falló
      </div>
      <p class="text-[15px] text-red-100">{{ call.failure_reason }}</p>
    </div>

    <!-- POST-CALL SUMMARY — what happened / outcome -->
    <div v-if="!isLive && call.assistant_summary"
      class="rounded-2xl border border-neon/30 bg-neon/5 p-5">
      <div class="flex items-center gap-2 text-neon font-bold text-sm uppercase tracking-wide mb-2">
        <FileText class="w-4 h-4" /> Resumen de la llamada
      </div>
      <p class="text-[15px] text-gray-100 whitespace-pre-wrap leading-relaxed">{{ call.assistant_summary }}</p>
    </div>
    <div v-else-if="summaryPending"
      class="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 flex items-center gap-2 text-sm text-gray-400">
      <UiSpinner :size="14" /> Generando resumen de la llamada…
    </div>

    <!-- LIVE AUDIO — hear the call as it happens -->
    <div
      v-if="isLive && call.twilio_call_sid"
      :class="['flex items-center gap-3 flex-wrap rounded-xl border px-4 py-3',
        listening && !audioBlocked ? 'border-neon/40 bg-neon/10' : 'border-white/10 bg-white/5']"
    >
      <!-- Primary button always toggles listening ON/OFF. -->
      <UiButton :variant="listening ? 'secondary' : 'primary'" @click="listening ? stopAudio() : startAudio()">
        <component :is="listening ? VolumeX : Headphones" class="w-4 h-4" />
        {{ listening ? 'Silenciar audio' : 'Escuchar en vivo' }}
      </UiButton>
      <span v-if="listening && !audioBlocked" class="inline-flex items-center gap-2 text-neon text-sm font-semibold">
        <span class="relative flex h-2 w-2">
          <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-neon opacity-75" />
          <span class="relative inline-flex rounded-full h-2 w-2 bg-neon" />
        </span>
        Escuchando en vivo
      </span>
      <!-- Auto-enabled but the browser suspended audio until a tap. -->
      <button v-else-if="listening && audioBlocked" @click="resumeAudio"
        class="inline-flex items-center gap-1.5 text-amber-300 text-sm font-semibold underline decoration-dotted">
        Toca para activar el sonido 🔊
      </button>
      <span v-else class="text-gray-400 text-sm">Audio de la llamada disponible</span>
      <span class="ml-auto text-[11px]" :class="socketReady ? 'text-neon/70' : 'text-gray-500'">
        {{ socketReady ? 'Control conectado' : 'Conectando…' }}
      </span>
    </div>

    <!-- LIVE QUESTION — the AI is waiting on you -->
    <div
      v-if="pendingQuestion"
      class="rounded-2xl border-2 border-amber-400/60 bg-amber-400/10 p-5 animate-[fade-in-up_0.25s_ease-out] shadow-[0_0_40px_-12px_rgba(251,191,36,0.6)]"
    >
      <div class="flex items-center gap-2 text-amber-300 font-bold text-sm uppercase tracking-wide mb-2">
        <HelpCircle class="w-4 h-4" /> La IA necesita tu respuesta
      </div>
      <p class="text-white text-lg font-medium mb-3">{{ pendingQuestion }}</p>
      <form @submit.prevent="sendAnswer" class="flex gap-2">
        <input
          ref="answerInput"
          v-model="answer"
          placeholder="Escribe la respuesta y presiona Enter…"
          class="flex-1 bg-black/30 border border-amber-400/40 rounded-lg px-4 py-3 text-white placeholder-amber-200/40 focus:outline-none focus:border-amber-400 text-[15px]"
        />
        <UiButton variant="primary" size="lg" type="submit" :disabled="!answer.trim()">
          <Send class="w-4 h-4" /> Responder
        </UiButton>
      </form>
      <p class="text-[11px] text-amber-200/70 mt-2">La IA le dijo a la persona que espere un segundo. Responde rápido.</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
      <!-- Left: transcript -->
      <div class="lg:col-span-2 space-y-4">
        <UiCard :title="isLive ? 'Conversación en vivo' : 'Conversación'" :padded="false">
          <div v-if="transcript.length" class="space-y-2.5 max-h-[560px] overflow-y-auto p-4" ref="transcriptEl">
            <div v-for="(line, i) in transcript" :key="i" :class="lineWrapClass(line.role)">
              <div :class="bubbleClass(line.role)">
                <span v-if="roleLabel(line.role)" class="block text-[10px] uppercase tracking-wide opacity-60 mb-0.5">{{ roleLabel(line.role) }}</span>
                {{ line.text }}
                <span class="block text-[10px] opacity-50 mt-0.5 font-mono">{{ line.at }}</span>
              </div>
            </div>
          </div>
          <UiEmptyState v-else :icon="MessageSquare" :title="isLive ? 'Esperando la llamada…' : 'Sin conversación'" />
        </UiCard>
      </div>

      <!-- Right: controls -->
      <div class="space-y-4">
        <!-- Keypad — press numbers like on a phone -->
        <UiCard v-if="isLive" title="Marcar teclas (tú)">
          <div class="grid grid-cols-3 gap-2">
            <button
              v-for="k in keypad"
              :key="k"
              type="button"
              @click="pressKey(k)"
              class="py-3 rounded-lg bg-white/5 border border-white/10 text-white text-lg font-semibold hover:bg-neon/15 hover:border-neon/40 active:scale-95 transition"
            >{{ k }}</button>
          </div>
          <p class="text-[11px] text-gray-500 mt-2">Marca tonos manualmente en la llamada. La IA también marca sola cuando detecta el menú.</p>
        </UiCard>

        <!-- Say something -->
        <UiCard v-if="isLive" title="Dictar a la IA">
          <form @submit.prevent="sendSay" class="flex gap-2">
            <input
              v-model="sayText"
              placeholder="Que diga exactamente…"
              class="flex-1 bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-gray-500 focus:outline-none focus:border-neon/50"
            />
            <UiButton variant="secondary" size="md" type="submit" :disabled="!sayText.trim()">
              <Send class="w-3.5 h-3.5" />
            </UiButton>
          </form>
        </UiCard>

        <!-- Info -->
        <UiCard title="Datos de la llamada">
          <dl class="space-y-2 text-sm">
            <div><dt class="text-[10.5px] text-gray-500 uppercase tracking-wide">Objetivo</dt><dd class="text-white whitespace-pre-wrap">{{ call.assistant_objective || call.custom_joke_prompt || '—' }}</dd></div>
            <div><dt class="text-[10.5px] text-gray-500 uppercase tracking-wide">Identidad IA</dt><dd class="text-white">{{ call.assistant_identity || '—' }}</dd></div>
            <div><dt class="text-[10.5px] text-gray-500 uppercase tracking-wide">Empresa</dt><dd class="text-white">{{ call.assistant_company || '—' }}</dd></div>
            <div v-if="call.assistant_context"><dt class="text-[10.5px] text-gray-500 uppercase tracking-wide">Datos</dt><dd class="text-gray-300 whitespace-pre-wrap text-[13px]">{{ call.assistant_context }}</dd></div>
            <div><dt class="text-[10.5px] text-gray-500 uppercase tracking-wide">Teléfono</dt><dd class="font-mono text-gray-300">{{ call.phone_number }}</dd></div>
          </dl>
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
import { useRoute, useRouter } from 'vue-router'
import axios from 'axios'
import { ArrowLeft, PhoneOff, HelpCircle, Send, Headphones, VolumeX, MessageSquare, RotateCw, FileText, AlertTriangle, Pencil } from 'lucide-vue-next'

import UiCard from '../components/UiCard.vue'
import UiButton from '../components/UiButton.vue'
import UiBadge from '../components/UiBadge.vue'
import UiSpinner from '../components/UiSpinner.vue'
import UiEmptyState from '../components/UiEmptyState.vue'
import { useToast } from '../composables/useToast.js'
import { useConfirm } from '../composables/useConfirm.js'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const confirm = useConfirm()

const call = ref(null)
const transcript = ref([])
const transcriptEl = ref(null)
const pendingQuestion = ref(null)
const answer = ref('')
const answerInput = ref(null)
const sayText = ref('')
const listening = ref(false)
const audioBlocked = ref(false)
const socketReady = ref(false)
const hangingUp = ref(false)
const retrying = ref(false)
let autoListenTried = false

const keypad = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '*', '0', '#']

let pollInterval, ws, audioCtx, gainNode, schedTime = 0, socketOpenedFor = null
let controlToken = ''
let nullPollStreak = 0

// Mulaw decode table for live audio
const MULAW = new Float32Array(256)
for (let i = 0; i < 256; i++) {
  let mu = ~i & 0xFF, sign = (mu & 0x80) ? -1 : 1
  mu &= 0x7F
  let e = (mu >> 4) & 7, m = mu & 0xF
  let s = (m << (e + 3)) + (1 << (e + 3)) - 132
  MULAW[i] = sign * Math.min(s, 32767) / 32768
}

const isLive = computed(() => ['calling', 'in_progress'].includes(call.value?.status))
// Show "generando resumen…" for a completed call until the summary arrives,
// bounded (~40s of polling) so a failed generation doesn't spin forever.
const summaryWaited = ref(0)
const summaryPending = computed(() =>
  call.value?.status === 'completed' && !call.value?.assistant_summary && summaryWaited.value <= 13)

function roleLabel(role) {
  return { ai: 'IA', human: 'Empresa', question: '❓ Pregunta a ti', answer: '✅ Tu respuesta', dtmf: 'Tecla', system: 'Sistema' }[role] || ''
}
function lineWrapClass(role) {
  return ['flex', role === 'ai' || role === 'dtmf' ? '' : 'flex-row-reverse', role === 'question' || role === 'answer' || role === 'system' ? 'justify-center' : '']
}
function bubbleClass(role) {
  const base = 'max-w-[85%] rounded-xl px-3 py-2 text-[13px] leading-relaxed'
  if (role === 'ai')       return `${base} bg-neon/10 border border-neon/15 text-gray-100`
  if (role === 'human')    return `${base} bg-blue-500/10 border border-blue-500/15 text-gray-100`
  if (role === 'dtmf')     return `${base} bg-white/5 border border-white/10 text-gray-300 font-mono`
  if (role === 'question') return `${base} bg-amber-400/10 border border-amber-400/25 text-amber-100 max-w-full`
  if (role === 'answer')   return `${base} bg-emerald-400/10 border border-emerald-400/25 text-emerald-100 max-w-full`
  return `${base} bg-white/5 border border-white/10 text-gray-400 max-w-full text-center`
}

async function fetchCall() {
  try {
    const { data } = await axios.get(`/admin-api/calls/${route.params.id}`)
    call.value = data
    if (data.live_transcript) {
      // Only auto-scroll if the operator is already near the bottom, so we don't
      // yank them away while they're re-reading an earlier line.
      const el = transcriptEl.value
      const wasNearBottom = !el || (el.scrollHeight - el.scrollTop - el.clientHeight < 80)
      transcript.value = JSON.parse(data.live_transcript)
      await nextTick()
      if (wasNearBottom && transcriptEl.value) transcriptEl.value.scrollTop = transcriptEl.value.scrollHeight
    }
    // Durable fallback for the live question (in case a socket event was missed).
    // Clear only after TWO consecutive null polls, so a poll that races ahead of
    // the DB write can't hide a banner the operator is answering.
    if (data.pending_question) {
      nullPollStreak = 0
      if (data.pending_question !== pendingQuestion.value) setQuestion(data.pending_question)
    } else if (++nullPollStreak >= 2 && pendingQuestion.value) {
      pendingQuestion.value = null
    }
    if (isLive.value) { ensureSocket(); maybeAutoListen() }
    if (data.status === 'completed' && !data.assistant_summary) summaryWaited.value++
  } catch { /* keep last */ }
}

function setQuestion(q) {
  nullPollStreak = 0
  pendingQuestion.value = q
  beep()
  nextTick(() => answerInput.value?.focus())
}

// ---- Live control socket (audio in + control out) ----
function ensureSocket() {
  const sid = call.value?.twilio_call_sid
  if (!sid || socketOpenedFor === sid) return
  socketOpenedFor = sid
  ws = new WebSocket(`wss://ws.vacilada.com/listen/${sid}`)
  ws.onopen = () => { socketReady.value = true }
  ws.onmessage = (ev) => {
    let msg
    try { msg = JSON.parse(ev.data) } catch { return }
    if (msg.type === 'audio' && msg.audio) return playAudio(msg.audio)
    if (msg.type !== 'event') return
    if (msg.event === 'connected' && msg.pendingQuestion) setQuestion(msg.pendingQuestion)
    else if (msg.event === 'supervisor_question') setQuestion(msg.question)
    else if (msg.event === 'supervisor_answered') pendingQuestion.value = null
    else if (msg.event === 'dtmf_sent') toast.info(`Marcado: ${msg.digits}`)
    else if (msg.event === 'assistant_hangup') toast.info('La IA está colgando')
    else if (msg.event === 'call_ended') {
      socketReady.value = false
      try { ws?.close() } catch {}
      ws = null; socketOpenedFor = null
      fetchCall()
    }
  }
  ws.onerror = () => { socketReady.value = false }
  ws.onclose = () => { socketReady.value = false; socketOpenedFor = null }
}

function send(obj) {
  if (ws && ws.readyState === WebSocket.OPEN) { ws.send(JSON.stringify({ ...obj, token: controlToken })); return true }
  toast.error('Sin conexión en vivo a la llamada')
  return false
}

function sendAnswer() {
  const text = answer.value.trim()
  if (!text) return
  if (send({ type: 'supervisor_answer', text })) {
    pendingQuestion.value = null
    answer.value = ''
  }
}
function pressKey(k) { send({ type: 'dtmf', digits: k }) }
function sendSay() {
  const text = sayText.value.trim()
  if (text && send({ type: 'say', text })) sayText.value = ''
}

// ---- Audio playback ----
function startAudio() {
  if (listening.value) return
  try {
    audioCtx = new (window.AudioContext || window.webkitAudioContext)({ sampleRate: 8000 })
    gainNode = audioCtx.createGain()
    gainNode.gain.value = 5.0
    gainNode.connect(audioCtx.destination)
    schedTime = 0
    listening.value = true
    ensureSocket()
    // Resume; if the browser blocks autoplay (no user gesture in this document)
    // the context stays 'suspended' and we prompt for a tap.
    Promise.resolve(audioCtx.resume?.()).then(() => {
      audioBlocked.value = audioCtx?.state === 'suspended'
    }).catch(() => { audioBlocked.value = true })
    audioBlocked.value = audioCtx.state === 'suspended'
  } catch { listening.value = false }
}
function stopAudio() {
  listening.value = false
  audioBlocked.value = false
  try { audioCtx?.close() } catch {}
  audioCtx = null
}
function resumeAudio() {
  Promise.resolve(audioCtx?.resume?.()).then(() => {
    audioBlocked.value = audioCtx?.state === 'suspended'
  })
}
// Auto-start the moment the call is live. Works out of the box when the operator
// arrived from the launch button (that click grants audio autoplay for this
// document); on a cold direct load the browser may require one tap to unblock.
// The "Silenciar audio" button always turns it back off.
function maybeAutoListen() {
  if (autoListenTried || !isLive.value || listening.value) return
  autoListenTried = true
  startAudio()
}
function playAudio(b64) {
  if (!listening.value || !audioCtx) return
  const bin = atob(b64)
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
}

function beep() {
  try {
    const ctx = new (window.AudioContext || window.webkitAudioContext)()
    ctx.resume?.()
    const o = ctx.createOscillator(), g = ctx.createGain()
    o.frequency.value = 880; o.connect(g); g.connect(ctx.destination)
    g.gain.setValueAtTime(0.001, ctx.currentTime)
    g.gain.exponentialRampToValueAtTime(0.25, ctx.currentTime + 0.02)
    g.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.35)
    o.start(); o.stop(ctx.currentTime + 0.36)
    setTimeout(() => { try { ctx.close() } catch {} }, 600)
  } catch {}
}

async function hangup() {
  if (!await confirm({ title: '¿Colgar la llamada?', body: 'La IA parará de inmediato.', danger: true, confirmLabel: 'Colgar' })) return
  hangingUp.value = true
  try { await axios.post(`/admin-api/calls/${route.params.id}/hangup`); await fetchCall(); toast.success('Llamada colgada') }
  catch (e) { toast.error(e) } finally { hangingUp.value = false }
}

// Relaunch the same assistant call (same phone, objective, context, identity,
// company, voice) and open the new call's console.
async function retry() {
  if (!call.value) return
  retrying.value = true
  try {
    const { data } = await axios.post('/admin-api/launch-assistant-call', {
      phone_number: call.value.phone_number,
      objective:    call.value.assistant_objective || call.value.custom_joke_prompt || '',
      context:      call.value.assistant_context || '',
      identity:     call.value.assistant_identity || '',
      company:      call.value.assistant_company || '',
      voice:        call.value.voice || 'ash',
    }, { timeout: 45000 })
    toast.success('Llamada reiniciada')
    router.push('/admin/assistant/' + data.call_id)
  } catch (e) {
    toast.error(e.response?.data?.error || 'No se pudo reiniciar la llamada')
  } finally { retrying.value = false }
}

// Prefill the launch form with this call's data (WITHOUT calling) so the
// operator can tweak it and launch. Passed via sessionStorage to avoid long
// URLs; AssistantCall.vue reads and clears it on mount.
function editAndRelaunch() {
  if (!call.value) return
  sessionStorage.setItem('assistant_prefill', JSON.stringify({
    phone_number: call.value.phone_number || '',
    objective:    call.value.assistant_objective || call.value.custom_joke_prompt || '',
    context:      call.value.assistant_context || '',
    identity:     call.value.assistant_identity || '',
    company:      call.value.assistant_company || '',
    voice:        call.value.voice || 'ash',
  }))
  router.push('/admin/assistant')
}

onMounted(async () => {
  try { const { data } = await axios.get('/admin-api/ws-control-token'); controlToken = data.token || '' } catch {}
  fetchCall()
  pollInterval = setInterval(fetchCall, 3000)
})
onUnmounted(() => {
  clearInterval(pollInterval)
  try { ws?.close() } catch {}
  try { audioCtx?.close() } catch {}
})
</script>
