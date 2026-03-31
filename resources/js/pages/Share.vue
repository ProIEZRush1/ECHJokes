<template>
    <div class="min-h-screen flex flex-col items-center justify-center px-4 py-12">
        <div class="w-full max-w-lg text-center">
            <div class="text-6xl mb-6">&#x1F3AD;</div>
            <h1 class="text-3xl font-bold font-mono text-neon mb-2">ECHJokes</h1>
            <p class="text-gray-400 mb-8">Escucha esta broma telefonica!</p>

            <div v-if="scenario" class="bg-matrix-800 border border-matrix-600 rounded-xl p-4 text-left mb-4">
                <h3 class="text-xs text-gray-500 mb-1">Escenario</h3>
                <p class="text-sm text-gray-300">{{ scenario }}</p>
            </div>

            <div v-if="recordingUrl" class="mb-8">
                <audio :src="recordingUrl" controls class="w-full" style="filter: sepia(100%) saturate(300%) brightness(70%) hue-rotate(60deg);" />
            </div>

            <router-link to="/" class="inline-block px-8 py-4 rounded-xl bg-neon text-matrix-900 font-bold text-lg hover:shadow-[var(--shadow-neon-lg)] transition-all">
                &#x1F4DE; Haz tu propia broma!
            </router-link>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import axios from 'axios';

const route = useRoute();
const scenario = ref('');
const recordingUrl = ref('');

onMounted(async () => {
    const shareData = window.__ECHJOKES__?.shareData;
    if (shareData) {
        scenario.value = shareData.scenario || shareData.joke_text || '';
        recordingUrl.value = shareData.recording_url || '';
        return;
    }
    try {
        const { data } = await axios.get(`/share/${route.params.sessionId}`, { headers: { Accept: 'application/json' } });
        scenario.value = data.scenario || data.joke_text || '';
        recordingUrl.value = data.recording_url || '';
    } catch { /* silent */ }
});
</script>
