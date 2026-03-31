<template>
    <div class="min-h-screen flex flex-col items-center justify-center px-4 py-12">
        <div class="w-full max-w-lg text-center">
            <h1 class="text-3xl font-bold font-mono text-neon mb-2">Chiste del dia</h1>
            <p class="text-gray-400 mb-8">{{ todayDate }}</p>

            <div v-if="joke" class="bg-matrix-800 border border-matrix-600 rounded-xl p-8 text-left mb-8">
                <p class="font-mono text-neon/80 text-lg leading-relaxed whitespace-pre-line">
                    {{ joke }}
                </p>
            </div>

            <div v-else class="text-gray-500 py-12">
                Cargando el chiste del dia...
            </div>

            <router-link
                to="/"
                class="inline-block px-8 py-4 rounded-xl bg-neon text-matrix-900 font-bold text-lg hover:shadow-[var(--shadow-neon-lg)] transition-all"
            >
                &#x1F4DE; Que se lo cuenten por telefono!
            </router-link>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';

const joke = ref('');

const todayDate = computed(() => {
    return new Date().toLocaleDateString('es-MX', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
});

onMounted(async () => {
    try {
        const { data } = await axios.get('/api/joke-of-the-day');
        joke.value = data.joke_text;
    } catch {
        joke.value = 'Que le dijo un techo a otro techo? Techo de menos!';
    }
});
</script>
