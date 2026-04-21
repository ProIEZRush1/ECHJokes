<template>
    <div class="min-h-screen flex flex-col items-center justify-center px-4 py-12">
        <div class="w-full max-w-xl text-center">
            <div class="text-6xl mb-4">&#x1F3AD;</div>
            <h1 class="text-3xl font-bold font-mono text-neon mb-2">ECHJokes</h1>
            <p class="text-gray-400 mb-6">Alguien te mando una broma telefonica!</p>

            <div v-if="scenario" class="bg-matrix-800 border border-matrix-600 rounded-xl p-4 text-left mb-4">
                <h3 class="text-xs text-gray-500 mb-1">Escenario</h3>
                <p class="text-sm text-gray-300">{{ scenario }}</p>
            </div>

            <div v-if="recordingUrl" class="mb-6">
                <audio :src="recordingUrl" controls class="w-full rounded-lg" />
            </div>

            <div v-if="recordingUrl" class="grid grid-cols-3 gap-3 mb-6">
                <a :href="whatsappUrl" target="_blank" rel="noopener" class="flex flex-col items-center gap-2 bg-[#25D366] hover:bg-[#1ebe5a] text-white rounded-xl p-3 transition">
                    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" fill="currentColor" viewBox="0 0 16 16"><path d="M13.601 2.326A7.854 7.854 0 0 0 7.994 0C3.627 0 .068 3.558.064 7.926c0 1.399.366 2.76 1.057 3.965L0 16l4.204-1.102a7.9 7.9 0 0 0 3.79.965h.004c4.368 0 7.926-3.558 7.93-7.93A7.9 7.9 0 0 0 13.6 2.326zM7.994 14.521a6.6 6.6 0 0 1-3.356-.92l-.24-.144-2.494.654.666-2.433-.156-.251a6.56 6.56 0 0 1-1.007-3.505c0-3.626 2.957-6.584 6.591-6.584a6.56 6.56 0 0 1 4.66 1.931 6.56 6.56 0 0 1 1.928 4.66c-.004 3.639-2.961 6.592-6.592 6.592m3.615-4.934c-.197-.099-1.17-.578-1.353-.646-.182-.065-.315-.099-.445.099-.133.197-.513.646-.627.775-.114.133-.232.148-.43.05-.197-.1-.836-.308-1.592-.985-.59-.525-.985-1.175-1.103-1.372-.114-.198-.011-.304.088-.403.087-.088.197-.232.296-.346.1-.114.133-.198.198-.33.065-.134.034-.248-.015-.347-.05-.099-.445-1.076-.612-1.47-.16-.389-.323-.335-.445-.34-.114-.007-.247-.007-.38-.007a.73.73 0 0 0-.529.247c-.182.198-.691.677-.691 1.654s.71 1.916.81 2.049c.098.133 1.394 2.132 3.383 2.992.47.205.84.326 1.129.418.475.152.904.129 1.246.08.38-.058 1.171-.48 1.338-.943.164-.464.164-.86.114-.943-.049-.084-.182-.133-.38-.232"/></svg>
                    <span class="text-xs font-semibold">WhatsApp</span>
                </a>
                <button @click="shareNative" class="flex flex-col items-center gap-2 bg-matrix-700 hover:bg-matrix-600 text-white rounded-xl p-3 transition border border-matrix-600">
                    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" fill="currentColor" viewBox="0 0 16 16"><path d="M13.5 1a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3M11 2.5a2.5 2.5 0 1 1 .603 1.628l-6.718 3.12a2.5 2.5 0 0 1 0 1.504l6.718 3.12a2.5 2.5 0 1 1-.488.876l-6.718-3.12a2.5 2.5 0 1 1 0-3.256l6.718-3.12A2.5 2.5 0 0 1 11 2.5m1.5 9.5a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3M2 7a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3"/></svg>
                    <span class="text-xs font-semibold">Compartir</span>
                </button>
                <button @click="copyLink" class="flex flex-col items-center gap-2 bg-matrix-700 hover:bg-matrix-600 text-white rounded-xl p-3 transition border border-matrix-600">
                    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" fill="currentColor" viewBox="0 0 16 16"><path d="M4.715 6.542 3.343 7.914a3 3 0 1 0 4.243 4.243l1.828-1.829A3 3 0 0 0 8.586 5.5L8 6.086a1 1 0 0 0-.154.199 2 2 0 0 1 .861 3.337L6.88 11.45a2 2 0 1 1-2.83-2.83l.793-.792a4 4 0 0 1-.128-1.287z"/><path d="M6.586 4.672A3 3 0 0 0 7.414 9.5l.775-.776a2 2 0 0 1-.896-3.346L9.12 3.55a2 2 0 1 1 2.83 2.83l-.793.792c.112.42.155.855.128 1.287l1.372-1.372a3 3 0 1 0-4.243-4.243z"/></svg>
                    <span class="text-xs font-semibold">{{ copied ? 'Copiado!' : 'Copiar link' }}</span>
                </button>
            </div>

            <a :href="signupUrl" class="block w-full bg-neon text-matrix-900 font-bold text-lg py-4 rounded-xl hover:shadow-[var(--shadow-neon-lg)] transition">
                &#x1F4DE; Haz tu propia broma GRATIS
            </a>
            <p class="text-xs text-gray-500 mt-3">Llamadas reales con IA en espanol. 2 bromas gratis al registrarte.</p>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import axios from 'axios';

const route = useRoute();
const scenario = ref('');
const recordingUrl = ref('');
const sessionId = ref('');
const copied = ref(false);

const shareUrl = computed(() => typeof window !== 'undefined' ? window.location.href : '');
const shareCaption = computed(() => `Mira esta broma telefonica que hice, me orine de risa ${shareUrl.value}`);
const whatsappUrl = computed(() => `https://wa.me/?text=${encodeURIComponent(shareCaption.value)}`);
const signupUrl = computed(() => `/?ref=${sessionId.value || ''}&utm_source=share&utm_medium=shared-call`);

function copyLink() {
    if (!navigator.clipboard) return;
    navigator.clipboard.writeText(shareUrl.value);
    copied.value = true;
    setTimeout(() => { copied.value = false; }, 2000);
}

async function shareNative() {
    if (navigator.share) {
        try { await navigator.share({ title: 'Broma telefonica de ECHJokes', text: shareCaption.value, url: shareUrl.value }); } catch (e) {}
    } else {
        copyLink();
    }
}

onMounted(async () => {
    const shareData = window.__ECHJOKES__?.shareData;
    if (shareData) {
        scenario.value = shareData.scenario || shareData.joke_text || '';
        recordingUrl.value = shareData.recording_url || '';
        sessionId.value = shareData.session_id || route.params.sessionId || '';
        return;
    }
    try {
        const sid = route.params.sessionId;
        sessionId.value = sid;
        const { data } = await axios.get(`/share/${sid}`, { headers: { Accept: 'application/json' } });
        scenario.value = data.scenario || data.joke_text || '';
        recordingUrl.value = data.recording_url || '';
    } catch { /* silent */ }
});
</script>
