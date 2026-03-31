<template>
    <div class="min-h-screen px-4 py-12 max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold font-mono text-neon mb-8">Mi cuenta</h1>

        <!-- Stats -->
        <div class="grid grid-cols-3 gap-4 mb-10">
            <div class="bg-matrix-800 border border-matrix-600 rounded-xl p-4 text-center">
                <div class="text-2xl font-bold text-neon">{{ stats.totalCalls }}</div>
                <div class="text-xs text-gray-500">Chistes enviados</div>
            </div>
            <div class="bg-matrix-800 border border-matrix-600 rounded-xl p-4 text-center">
                <div class="text-2xl font-bold text-neon">{{ stats.creditsRemaining }}</div>
                <div class="text-xs text-gray-500">Creditos restantes</div>
            </div>
            <div class="bg-matrix-800 border border-matrix-600 rounded-xl p-4 text-center">
                <div class="text-2xl font-bold text-neon">{{ stats.plan }}</div>
                <div class="text-xs text-gray-500">Plan actual</div>
            </div>
        </div>

        <!-- Call History -->
        <h2 class="text-xl font-bold text-white mb-4">Historial de llamadas</h2>

        <div v-if="calls.length === 0" class="text-gray-500 text-center py-8">
            No has enviado ningun chiste aun.
        </div>

        <div v-else class="space-y-3">
            <div
                v-for="call in calls"
                :key="call.id"
                class="bg-matrix-800 border border-matrix-600 rounded-xl p-4 flex items-center justify-between"
            >
                <div>
                    <div class="text-sm font-mono text-white">
                        {{ call.phone_number?.replace(/(.{3})(.*)(.{2})/, '$1 *** **$3') }}
                    </div>
                    <div class="text-xs text-gray-500">
                        {{ call.joke_category }} · {{ call.status_label }} · {{ formatDate(call.created_at) }}
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <router-link
                        v-if="call.status === 'completed'"
                        :to="'/call/' + call.id + '/status'"
                        class="text-xs text-neon hover:underline"
                    >
                        Ver
                    </router-link>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="mt-10 flex gap-4">
            <router-link
                to="/"
                class="px-6 py-3 rounded-xl bg-neon text-matrix-900 font-bold hover:shadow-[var(--shadow-neon)] transition-all"
            >
                Enviar otro chiste
            </router-link>
            <router-link
                to="/pricing"
                class="px-6 py-3 rounded-xl bg-matrix-700 text-white font-medium hover:bg-matrix-600 transition-colors"
            >
                Ver planes
            </router-link>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';

const stats = ref({ totalCalls: 0, creditsRemaining: 0, plan: 'Gratis' });
const calls = ref([]);

function formatDate(dateStr) {
    return new Date(dateStr).toLocaleDateString('es-MX', { day: 'numeric', month: 'short' });
}

onMounted(async () => {
    // TODO: Fetch from /api/dashboard when auth is ready
});
</script>
