<template>
    <div class="min-h-screen flex flex-col">
        <!-- Navbar -->
        <nav class="w-full px-4 md:px-8 py-4 flex items-center justify-between">
            <router-link to="/" class="text-lg font-bold font-mono text-neon">ECHJokes</router-link>
            <div class="flex items-center gap-3 md:gap-5">
                <router-link to="/pricing" class="text-sm text-gray-400 hover:text-neon transition">Precios</router-link>
                <template v-if="user">
                    <router-link to="/dashboard" class="text-sm text-gray-400 hover:text-neon transition">Mis Bromas</router-link>
                    <router-link to="/dashboard/new" class="hidden md:inline-block px-3 py-1.5 rounded-lg bg-neon/10 border border-neon/20 text-neon text-sm font-medium hover:bg-neon/20 transition">
                        {{ user.credits }} cr
                    </router-link>
                </template>
                <template v-else>
                    <router-link to="/login" class="px-4 py-1.5 rounded-lg bg-neon text-matrix-900 text-sm font-bold hover:shadow-neon transition">
                        Entrar
                    </router-link>
                </template>
            </div>
        </nav>

        <div class="flex-1 flex flex-col items-center justify-center px-4 py-4 md:py-8">
        <!-- Hero -->
        <div class="text-center mb-8 md:mb-12">
            <div class="text-5xl md:text-7xl mb-4 md:mb-6 animate-[ring_1s_ease-in-out_infinite]">&#x1F4DE;</div>
            <h1 class="text-3xl md:text-6xl font-bold font-mono text-neon animate-[glow_1.5s_ease-in-out_infinite_alternate] mb-3 md:mb-4">ECHJokes</h1>
            <p class="text-lg md:text-2xl text-gray-400 max-w-lg mx-auto px-2">Bromas telefonicas con IA. Tu describes la situacion, la IA hace la llamada.</p>
        </div>

        <!-- Form -->
        <div class="w-full max-w-md bg-matrix-800 border border-matrix-600 rounded-2xl p-5 md:p-8 shadow-lg">
            <div class="text-center mb-5">
                <span v-if="user && user.credits > 0" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-neon/20 text-neon border border-neon/30">
                    {{ user.credits }} credito{{ user.credits > 1 ? 's' : '' }} disponible{{ user.credits > 1 ? 's' : '' }}
                </span>
                <span v-else-if="user" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-yellow-500/20 text-yellow-400 border border-yellow-500/30">
                    Sin creditos - <router-link to="/pricing" class="underline ml-1">comprar plan</router-link>
                </span>
                <span v-else class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-neon/20 text-neon border border-neon/30">
                    Prueba gratis - 1 llamada de hasta 3 min
                </span>
            </div>

            <form @submit.prevent="handleSubmit">
                <!-- Phone -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-400 mb-2">Numero que recibira la llamada</label>
                    <div class="flex items-center bg-matrix-700 border border-matrix-600 rounded-xl overflow-hidden focus-within:border-neon/50 transition-colors">
                        <span class="px-3 md:px-4 py-3 text-gray-400 font-mono border-r border-matrix-600 flex items-center gap-1.5 text-sm">
                            <span class="text-base">&#x1F1F2;&#x1F1FD;</span> +52
                        </span>
                        <input v-model="phone" type="tel" maxlength="10" placeholder="55 1234 5678"
                            class="flex-1 bg-transparent px-3 md:px-4 py-3 text-white font-mono text-base md:text-lg outline-none placeholder:text-gray-600"
                            @input="formatPhone" />
                    </div>
                    <p v-if="errors.phone" class="mt-2 text-sm text-red-400">{{ errors.phone }}</p>
                </div>

                <!-- Voice -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-400 mb-2">Voz de la IA</label>
                    <div class="grid grid-cols-2 gap-2">
                        <button type="button" @click="voice = 'ash'"
                            :class="['flex items-center gap-2 p-2.5 md:p-3 rounded-xl border transition-all text-sm',
                                voice === 'ash' ? 'border-neon bg-neon/10 text-white' : 'border-matrix-600 text-gray-400']">
                            <span class="text-xl">&#x1F468;</span>
                            <div class="text-left"><p class="font-semibold">Hombre</p><p class="text-[10px] opacity-60">Voz natural</p></div>
                        </button>
                        <button type="button" @click="voice = 'coral'"
                            :class="['flex items-center gap-2 p-2.5 md:p-3 rounded-xl border transition-all text-sm',
                                voice === 'coral' ? 'border-neon bg-neon/10 text-white' : 'border-matrix-600 text-gray-400']">
                            <span class="text-xl">&#x1F469;</span>
                            <div class="text-left"><p class="font-semibold">Mujer</p><p class="text-[10px] opacity-60">Voz natural</p></div>
                        </button>
                    </div>
                </div>

                <!-- Scenario -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-400 mb-2">&#x1F3AD; Describe la broma</label>
                    <textarea v-model="scenario" maxlength="500" rows="3"
                        placeholder="Ej: 'Que llamen de la administracion del condominio diciendo que la lavadora hace mucho ruido...'"
                        class="w-full bg-matrix-700 border border-matrix-600 rounded-xl px-3 md:px-4 py-3 text-white text-sm outline-none focus:border-neon/50 transition-colors resize-none placeholder:text-gray-600 leading-relaxed"
                    ></textarea>
                    <div class="flex justify-between mt-1">
                        <p class="text-xs text-gray-600">La IA creara el personaje</p>
                        <p class="text-xs" :class="scenario.length > 450 ? 'text-yellow-500' : 'text-gray-600'">{{ scenario.length }}/500</p>
                    </div>
                    <p v-if="errors.scenario" class="mt-1 text-sm text-red-400">{{ errors.scenario }}</p>
                </div>

                <!-- Style -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-400 mb-2">Estilo de voz <span class="text-red-400">*</span></label>
                    <div class="flex gap-2">
                        <input v-model="style" placeholder="Ej: Formal y serio, Chistoso, Nervioso..."
                            class="flex-1 bg-matrix-700 border border-matrix-600 rounded-xl px-3 md:px-4 py-2.5 text-white text-sm outline-none focus:border-neon/50 transition-colors placeholder:text-gray-600" />
                        <button type="button" @click="generateStyle" :disabled="generating || !scenario.trim()"
                            class="px-3 py-2.5 rounded-xl bg-matrix-700 border border-matrix-600 text-xs text-gray-400 hover:text-neon hover:border-neon/30 transition whitespace-nowrap disabled:opacity-30"
                            title="Generar estilo con IA">
                            {{ generating ? '...' : 'Auto IA' }}
                        </button>
                    </div>
                </div>

                <!-- Presets -->
                <div class="mb-5" v-if="presets.length">
                    <p class="text-xs text-gray-500 mb-2">O elige una idea:</p>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                        <button v-for="p in presets" :key="p.id" type="button"
                            @click="usePreset(p)"
                            :class="['flex items-center gap-2 p-2 md:p-2.5 rounded-xl border text-left transition-all text-xs',
                                activePreset === p.id ? 'border-neon bg-neon/10 text-white' : 'border-matrix-600 text-gray-400 hover:border-neon/30 hover:text-gray-300']">
                            <span class="text-lg flex-shrink-0">{{ p.emoji }}</span>
                            <span class="truncate">{{ p.label }}</span>
                        </button>
                    </div>
                </div>

                <!-- Submit -->
                <button type="submit" :disabled="loading || phone.length < 10 || !scenario.trim() || !style.trim()"
                    class="w-full py-3.5 md:py-4 rounded-xl bg-neon text-matrix-900 font-bold text-base md:text-lg shadow-[var(--shadow-neon)] hover:shadow-[var(--shadow-neon-lg)] transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                    <span v-if="loading" class="flex items-center justify-center gap-2">
                        <svg class="animate-spin h-5 w-5" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
                        Iniciando llamada...
                    </span>
                    <span v-else-if="user && user.credits > 0">Hacer llamada ({{ user.credits }} cr)</span>
                    <span v-else-if="user">Sin creditos</span>
                    <span v-else>Hacer llamada de prueba gratis</span>
                </button>

                <p v-if="errors.general" class="mt-4 text-sm text-red-400 text-center">{{ errors.general }}</p>

                <div v-if="trialUsed" class="mt-4 p-3 md:p-4 rounded-xl bg-matrix-700 border border-neon/20 text-center">
                    <p class="text-sm text-gray-300 mb-2">Ya usaste tu prueba gratuita</p>
                    <router-link to="/pricing" class="text-neon font-bold text-sm hover:underline">Ver planes desde $29 MXN &rarr;</router-link>
                </div>
            </form>

            <p v-if="!user" class="mt-5 text-[10px] md:text-xs text-gray-600 text-center">Prueba gratuita: 1 llamada de hasta 3 minutos. Sin tarjeta.</p>
        </div>

        <!-- Example -->
        <div class="w-full max-w-md mt-8 md:mt-12">
            <h3 class="text-sm font-medium text-gray-500 mb-4 text-center">Ejemplo de conversacion</h3>
            <div class="bg-matrix-800 border border-matrix-600 rounded-xl p-4 md:p-6 font-mono text-xs md:text-sm space-y-2 md:space-y-3">
                <div class="text-gray-500 text-[10px] md:text-xs italic mb-2">La IA llama como administrador del condominio...</div>
                <div class="text-neon/80"><span class="text-gray-500">IA:</span> Oye hola, disculpa. Mira, te hablo de la administracion del condominio...</div>
                <div class="text-white/60"><span class="text-gray-500">Persona:</span> Ah si? Que paso?</div>
                <div class="text-neon/80"><span class="text-gray-500">IA:</span> Fijate que nos estan reportando un ruido como de helicoptero que sale de tu depa...</div>
                <div class="text-white/60"><span class="text-gray-500">Persona:</span> Jajaja es la lavadora!</div>
            </div>
        </div>

        </div>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';

const router = useRouter();
const phone = ref('');
const scenario = ref('');
const voice = ref('ash');
const style = ref('');
const generating = ref(false);
const loading = ref(false);
const trialUsed = ref(false);
const activePreset = ref(null);
const presets = ref([]);
const user = ref(null);
const errors = reactive({ phone: '', scenario: '', general: '' });

onMounted(async () => {
    try {
        const [pr, me] = await Promise.all([
            axios.get('/api/presets'),
            axios.get('/user-api/me').catch(() => null),
        ]);
        presets.value = pr.data;
        if (me?.data?.user) user.value = me.data.user;
    } catch {}
});

function usePreset(p) {
    scenario.value = p.scenario;
    if (p.voice) voice.value = p.voice;
    activePreset.value = p.id;
}

async function generateStyle() {
    if (!scenario.value.trim() || generating.value) return;
    generating.value = true;
    try {
        const { data } = await axios.post('/api/generate-style', { scenario: scenario.value.trim() });
        if (data.style) style.value = data.style;
    } catch {} finally { generating.value = false; }
}

function formatPhone(e) { phone.value = e.target.value.replace(/\D/g, '').slice(0, 10); }

async function handleSubmit() {
    errors.phone = ''; errors.scenario = ''; errors.general = '';
    trialUsed.value = false; activePreset.value = null;

    const digits = phone.value.replace(/\D/g, '');
    if (digits.length !== 10) { errors.phone = 'El numero debe tener 10 digitos.'; return; }
    if (!scenario.value.trim() || scenario.value.trim().length < 10) { errors.scenario = 'Describe la situacion (minimo 10 caracteres).'; return; }

    // If user has no credits, redirect to pricing
    if (user.value && user.value.credits <= 0) {
        router.push('/pricing');
        return;
    }

    loading.value = true;
    try {
        // Use paid API if logged in with credits, trial otherwise
        const endpoint = user.value ? '/user-api/make-call' : '/trial';
        const { data } = await axios.post(endpoint, {
            phone_number: digits,
            scenario: scenario.value.trim(),
            character: style.value,
            voice: voice.value,
        });
        router.push(data.redirect);
    } catch (err) {
        if (err.response?.status === 429 && err.response?.data?.show_plans) {
            trialUsed.value = true;
            errors.general = err.response.data.error;
        } else if (err.response?.status === 402) {
            router.push('/pricing');
        } else {
            errors.general = err.response?.data?.error || 'Algo salio mal.';
        }
    } finally { loading.value = false; }
}
</script>
