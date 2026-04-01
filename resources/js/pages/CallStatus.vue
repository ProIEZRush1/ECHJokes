<template>
    <div class="min-h-screen flex flex-col items-center justify-center px-4 py-12">
        <!-- Back button -->
        <div class="w-full max-w-lg mb-4">
            <router-link to="/" class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-neon transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                Volver al inicio
            </router-link>
        </div>

        <div class="w-full max-w-lg text-center">
            <!-- Phone Animation -->
            <div class="text-7xl mb-8" :class="{ 'animate-[ring_1s_ease-in-out_infinite]': isRinging }">
                {{ statusEmoji }}
            </div>

            <h2 class="text-2xl font-bold font-mono text-neon mb-2">
                {{ currentLabel }}
            </h2>
            <p class="text-gray-400 mb-10">{{ statusDescription }}</p>

            <!-- Status Stepper -->
            <div class="flex items-center justify-center gap-2 mb-12">
                <div
                    v-for="(step, i) in steps"
                    :key="step.status"
                    class="flex items-center"
                >
                    <div
                        :class="[
                            'w-3 h-3 rounded-full transition-all duration-500',
                            stepIndex >= i
                                ? 'bg-neon shadow-[var(--shadow-neon)] scale-110'
                                : 'bg-matrix-600',
                            currentStepIndex === i && !isTerminal
                                ? 'animate-[pulse-neon_2s_ease-in-out_infinite]'
                                : '',
                        ]"
                    />
                    <div
                        v-if="i < steps.length - 1"
                        :class="['w-8 h-0.5 transition-all duration-500', stepIndex > i ? 'bg-neon' : 'bg-matrix-600']"
                    />
                </div>
            </div>

            <div class="grid grid-cols-5 gap-1 text-xs text-gray-500 mb-12">
                <span v-for="step in steps" :key="step.status" class="text-center">{{ step.label }}</span>
            </div>

            <!-- Scenario -->
            <div v-if="scenario" class="bg-matrix-800 border border-matrix-600 rounded-xl p-4 text-left mb-6">
                <h3 class="text-xs font-medium text-gray-500 mb-2 uppercase tracking-wider">Escenario</h3>
                <p class="text-sm text-gray-300">{{ scenario }}</p>
            </div>

            <!-- Live Conversation -->
            <div v-if="conversation.length > 0" class="bg-matrix-800 border border-matrix-600 rounded-xl p-6 text-left mb-8">
                <h3 class="text-xs font-medium text-gray-500 mb-3 uppercase tracking-wider">Conversacion</h3>
                <div class="space-y-2 font-mono text-sm">
                    <div v-for="(turn, i) in conversation" :key="i" :class="turn.role === 'ai' ? 'text-neon/80' : 'text-white/60'">
                        <span class="text-gray-500">{{ turn.role === 'ai' ? 'IA:' : 'Persona:' }}</span>
                        {{ turn.text }}
                    </div>
                </div>
            </div>

            <!-- Error -->
            <div v-if="failureReason" class="bg-red-900/20 border border-red-800 rounded-xl p-6 mb-8">
                <p class="text-red-400 text-sm">{{ translateFailure(failureReason) }}</p>
                <router-link to="/" class="inline-block mt-4 px-6 py-2 rounded-lg bg-neon text-matrix-900 font-medium text-sm">
                    Intentar de nuevo
                </router-link>
            </div>

            <!-- Recording -->
            <div v-if="recordingUrl && isTerminal" class="bg-matrix-800 border border-matrix-600 rounded-xl p-4 md:p-6 mb-6">
                <h3 class="text-xs font-medium text-gray-500 mb-3 uppercase tracking-wider">Grabacion</h3>
                <audio controls class="w-full mb-3" :src="recordingUrl" preload="metadata"
                    style="filter: hue-rotate(100deg) saturate(1.5);"></audio>
                <a :href="recordingUrl" download class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-matrix-700 border border-matrix-600 text-sm text-gray-300 hover:text-neon hover:border-neon/30 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Descargar grabacion
                </a>
            </div>

            <!-- Completed -->
            <div v-if="status === 'completed'" class="space-y-4">
                <p class="text-neon text-lg font-medium">Broma completada!</p>
                <p v-if="callDuration" class="text-gray-400 text-sm">Duracion: {{ callDuration }} segundos</p>
                <router-link :to="nextCallLink" class="inline-block mt-4 px-8 py-3 rounded-xl bg-neon text-matrix-900 font-bold hover:shadow-[var(--shadow-neon-lg)] transition-all">
                    Hacer otra broma
                </router-link>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useRoute } from 'vue-router';
import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

const route = useRoute();
const jokeCallId = route.params.id;

const status = ref('pending_payment');
const currentLabel = ref('Cargando...');
const scenario = ref('');
const conversation = ref([]);
const callDuration = ref(null);
const failureReason = ref(null);
const recordingUrl = ref(null);
const sessionId = ref(null);
const userCredits = ref(null);
let echo = null;

// Check if user is logged in to decide where "Hacer otra broma" goes
axios.get('/user-api/me').then(r => { userCredits.value = r.data.user?.credits ?? 0 }).catch(() => {});

const nextCallLink = computed(() => {
    if (userCredits.value !== null && userCredits.value > 0) return '/dashboard/new';
    if (userCredits.value === 0) return '/pricing';
    return '/'; // not logged in, go to landing (trial)
});

const steps = [
    { status: 'paid', label: 'Pago' },
    { status: 'generating_joke', label: 'Guion' },
    { status: 'generating_audio', label: 'Audio' },
    { status: 'calling', label: 'Llamando' },
    { status: 'completed', label: 'Listo' },
];

const statusOrder = ['pending_payment', 'paid', 'generating_joke', 'generating_audio', 'queued_for_call', 'calling', 'in_progress', 'completed'];

const stepIndex = computed(() => {
    const idx = statusOrder.indexOf(status.value);
    if (status.value === 'completed') return 4;
    if (idx <= 1) return 0;
    if (idx <= 2) return 1;
    if (idx <= 3) return 2;
    return 3;
});
const currentStepIndex = computed(() => stepIndex.value);
const isTerminal = computed(() => ['completed', 'failed', 'refunded'].includes(status.value));
const isRinging = computed(() => ['calling', 'queued_for_call'].includes(status.value));

const statusEmoji = computed(() => {
    if (status.value === 'completed') return '\u2705';
    if (status.value === 'failed') return '\u274C';
    if (isRinging.value) return '\uD83D\uDCDE';
    if (status.value === 'in_progress') return '\uD83D\uDDE3\uFE0F';
    return '\u23F3';
});

const statusDescription = computed(() => ({
    pending_payment: 'Estamos verificando tu pago. Esto toma solo unos segundos...',
    paid: 'Pago confirmado! Estamos preparando todo para tu broma...',
    generating_joke: 'La IA esta creando el guion perfecto y el personaje para la llamada...',
    generating_audio: 'Preparando la voz del personaje para que suene lo mas real posible...',
    queued_for_call: 'Tu llamada esta en cola. En unos segundos empezamos a marcar...',
    calling: 'Estamos marcando el numero! En cualquier momento contestan...',
    in_progress: 'La IA esta hablando en vivo! La conversacion esta sucediendo ahora mismo.',
    completed: 'La broma se completo exitosamente! Puedes escuchar la grabacion abajo.',
    failed: 'La llamada no se pudo completar. Puede ser que el numero estaba ocupado, no contesto, o hubo un error de conexion.',
    voicemail: 'La llamada fue a buzon de voz. La persona no contesto.',
    refunded: 'Se proceso tu reembolso. El credito fue devuelto a tu cuenta.',
}[status.value] || ''));

function updateFromData(data) {
    status.value = data.status;
    currentLabel.value = data.status_label;
    if (data.scenario) scenario.value = data.scenario;
    if (data.conversation?.length) conversation.value = data.conversation;
    if (data.call_duration_seconds) callDuration.value = data.call_duration_seconds;
    if (data.failure_reason) failureReason.value = data.failure_reason;
    if (data.recording_url) recordingUrl.value = data.recording_url;
}

function translateFailure(reason) {
    if (!reason) return '';
    const r = reason.toLowerCase();
    if (r.includes('busy')) return 'El numero estaba ocupado. Intenta de nuevo en unos minutos.';
    if (r.includes('no-answer') || r.includes('no answer')) return 'No contestaron la llamada. Intenta mas tarde.';
    if (r.includes('canceled') || r.includes('cancelled')) return 'La llamada fue cancelada.';
    if (r.includes('failed')) return 'La llamada fallo. Puede ser un problema con el numero.';
    if (r.includes('buzon') || r.includes('voicemail')) return 'La llamada fue al buzon de voz. La persona no contesto.';
    if (r.includes('timeout')) return 'La llamada se agoto el tiempo de espera.';
    return reason;
}

function setupEcho() {
    if (!sessionId.value) return;
    const config = window.__ECHJOKES__?.reverb || {};
    window.Pusher = Pusher;
    echo = new Echo({
        broadcaster: 'reverb',
        key: config.key,
        wsHost: config.host,
        wsPort: config.port,
        wssPort: config.port,
        forceTLS: config.scheme === 'https',
        enabledTransports: ['ws', 'wss'],
    });
    echo.channel('joke-call.' + sessionId.value)
        .listen('.App\\Events\\JokeCallStatusUpdated', updateFromData);
}

let pollInterval = null;

async function fetchStatus() {
    try {
        const { data } = await axios.get(`/call/${jokeCallId}/status`, { headers: { Accept: 'application/json' } });
        updateFromData(data);
        if (!sessionId.value && data.session_id) {
            sessionId.value = data.session_id;
            setupEcho();
        }
        // Stop polling when terminal
        if (data.is_terminal && pollInterval) {
            clearInterval(pollInterval);
            pollInterval = null;
        }
    } catch {
        status.value = 'failed';
        currentLabel.value = 'Error';
        failureReason.value = 'No se pudo cargar el estado de la llamada.';
    }
}

onMounted(() => {
    fetchStatus();
    // Poll every 3s as fallback (WebSocket may not be configured)
    pollInterval = setInterval(fetchStatus, 3000);
});

onUnmounted(() => {
    if (pollInterval) clearInterval(pollInterval);
    if (echo && sessionId.value) echo.leave('joke-call.' + sessionId.value);
});
</script>
