<template>
    <div class="min-h-screen flex flex-col items-center justify-center px-4 py-12">
        <div class="w-full max-w-lg text-center">
            <div class="text-7xl mb-6">&#x1F389;</div>
            <h2 class="text-3xl font-bold font-mono text-neon mb-2">Broma completada!</h2>

            <!-- Scenario -->
            <div v-if="jokeCall?.scenario" class="mt-6 bg-matrix-800 border border-matrix-600 rounded-xl p-4 text-left">
                <h3 class="text-xs font-medium text-gray-500 mb-2 uppercase tracking-wider">Escenario</h3>
                <p class="text-sm text-gray-300">{{ jokeCall.scenario }}</p>
            </div>

            <!-- Conversation transcript -->
            <div v-if="jokeCall?.conversation?.length" class="mt-4 bg-matrix-800 border border-matrix-600 rounded-xl p-6 text-left">
                <h3 class="text-xs font-medium text-gray-500 mb-3 uppercase tracking-wider">Conversacion</h3>
                <div class="space-y-2 font-mono text-sm max-h-64 overflow-y-auto">
                    <div v-for="(turn, i) in jokeCall.conversation" :key="i" :class="turn.role === 'ai' ? 'text-neon/80' : 'text-white/60'">
                        <span class="text-gray-500">{{ turn.role === 'ai' ? 'IA:' : 'Persona:' }}</span>
                        {{ turn.text }}
                    </div>
                </div>
                <div class="mt-3 pt-3 border-t border-matrix-600 flex items-center justify-between text-xs text-gray-500">
                    <span>{{ jokeCall.conversation.length }} turnos</span>
                    <span v-if="jokeCall.call_duration_seconds">Duracion: {{ jokeCall.call_duration_seconds }}s</span>
                </div>
            </div>

            <!-- Recording Player -->
            <div v-if="jokeCall?.recording_url" class="mt-4 bg-matrix-800 border border-matrix-600 rounded-xl p-6">
                <h3 class="text-xs font-medium text-gray-500 mb-3 uppercase tracking-wider">Grabacion</h3>
                <audio :src="jokeCall.recording_url" controls class="w-full" style="filter: sepia(100%) saturate(300%) brightness(70%) hue-rotate(60deg);" />
            </div>

            <div v-else-if="jokeCall && !jokeCall.recording_url && pollCount < 12" class="mt-4 text-gray-500 text-sm flex items-center justify-center gap-2">
                <svg class="animate-spin h-4 w-4" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
                Preparando grabacion...
            </div>

            <!-- Share -->
            <div v-if="jokeCall" class="mt-6 flex flex-col gap-3">
                <button @click="copyShareLink" class="w-full py-3 rounded-xl bg-matrix-700 text-white font-medium hover:bg-matrix-600 transition-colors">
                    {{ copied ? '&#x2705; Link copiado!' : '&#x1F517; Copiar link' }}
                </button>
                <a :href="whatsappShareUrl" target="_blank" class="block w-full py-3 rounded-xl bg-green-600 text-white font-medium text-center hover:bg-green-700 transition-colors">
                    &#x1F4AC; Compartir por WhatsApp
                </a>
            </div>

            <router-link to="/" class="inline-block mt-8 px-8 py-3 rounded-xl bg-neon text-matrix-900 font-bold hover:shadow-[var(--shadow-neon-lg)] transition-all">
                Hacer otra broma
            </router-link>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useRoute } from 'vue-router';
import axios from 'axios';

const route = useRoute();
const jokeCall = ref(null);
const copied = ref(false);
const pollCount = ref(0);
let pollInterval = null;

const shareUrl = computed(() => jokeCall.value ? `${window.location.origin}/share/${jokeCall.value.session_id}` : '');
const whatsappShareUrl = computed(() => `https://wa.me/?text=${encodeURIComponent('Escucha esta broma telefonica de ECHJokes! ' + shareUrl.value)}`);

function copyShareLink() {
    navigator.clipboard.writeText(shareUrl.value);
    copied.value = true;
    setTimeout(() => copied.value = false, 3000);
}

async function fetchData() {
    try {
        const { data } = await axios.get(`/call/${route.params.id}/status`, { headers: { Accept: 'application/json' } });
        jokeCall.value = data;
        if (data.recording_url && pollInterval) { clearInterval(pollInterval); pollInterval = null; }
    } catch { /* silent */ }
}

onMounted(async () => {
    await fetchData();
    if (jokeCall.value && !jokeCall.value.recording_url) {
        pollInterval = setInterval(() => { pollCount.value++; if (pollCount.value >= 12) { clearInterval(pollInterval); return; } fetchData(); }, 5000);
    }
});

onUnmounted(() => { if (pollInterval) clearInterval(pollInterval); });
</script>
