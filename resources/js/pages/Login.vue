<template>
    <div class="min-h-screen flex flex-col items-center justify-center px-4 py-12">
        <div class="w-full max-w-sm">
            <h1 class="text-3xl font-bold font-mono text-neon mb-2 text-center">Iniciar sesion</h1>
            <p class="text-gray-400 text-center mb-8">
                Te enviamos un link magico por correo.
            </p>

            <form @submit.prevent="sendMagicLink" class="space-y-6">
                <div>
                    <label class="block text-sm text-gray-400 mb-2">Correo electronico</label>
                    <input
                        v-model="email"
                        type="email"
                        required
                        placeholder="tu@correo.com"
                        class="w-full bg-matrix-700 border border-matrix-600 rounded-xl px-4 py-3 text-white outline-none focus:border-neon/50 transition-colors"
                    />
                </div>

                <button
                    type="submit"
                    :disabled="loading || sent"
                    class="w-full py-3 rounded-xl bg-neon text-matrix-900 font-bold disabled:opacity-50 transition-all"
                >
                    <span v-if="sent">&#x2705; Link enviado! Revisa tu correo.</span>
                    <span v-else-if="loading">Enviando...</span>
                    <span v-else>Enviar link magico</span>
                </button>

                <p v-if="error" class="text-red-400 text-sm text-center">{{ error }}</p>
            </form>

            <p class="text-center mt-8">
                <router-link to="/" class="text-neon/60 hover:text-neon text-sm">
                    ← Volver al inicio
                </router-link>
            </p>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';

const email = ref('');
const loading = ref(false);
const sent = ref(false);
const error = ref('');

async function sendMagicLink() {
    // TODO: Implement magic link auth endpoint
    loading.value = true;
    error.value = '';

    setTimeout(() => {
        sent.value = true;
        loading.value = false;
    }, 1000);
}
</script>
