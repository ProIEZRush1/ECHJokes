<template>
    <div class="min-h-screen flex flex-col">
        <!-- Navbar -->
        <nav class="w-full px-4 md:px-8 py-4 flex items-center justify-between">
            <router-link to="/" class="text-lg font-bold font-mono text-neon">Vacilada</router-link>
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
        <!-- Facebook Ad Banner -->
        <div v-if="fromAd && !user" class="w-full max-w-md mb-4 p-3 md:p-4 rounded-2xl bg-neon/10 border border-neon/30 text-center animate-pulse">
            <p class="text-sm md:text-base font-bold text-neon">&#x1F381; Tu primera broma es GRATIS</p>
            <p class="text-xs text-gray-400 mt-1">Solo pon el numero y elige una idea. Sin registro, sin tarjeta.</p>
        </div>

        <!-- Hero -->
        <div class="text-center mb-4 md:mb-8">
            <div class="text-4xl md:text-7xl mb-3 md:mb-6 animate-[ring_1s_ease-in-out_infinite]">&#x1F4DE;</div>
            <h1 class="text-2xl md:text-6xl font-bold font-mono text-neon animate-[glow_1.5s_ease-in-out_infinite_alternate] mb-2 md:mb-4">Vacilada</h1>
            <p class="text-base md:text-2xl text-gray-400 max-w-lg mx-auto px-2">Bromas telef&oacute;nicas con IA. T&uacute; describes la situaci&oacute;n, la IA hace la llamada.</p>
        </div>

        <!-- Social Proof (moved above form) -->
        <div class="w-full max-w-md mb-4 md:mb-6 grid grid-cols-3 gap-2 md:gap-3 text-center">
            <div class="bg-matrix-800 border border-matrix-600 rounded-xl p-2 md:p-3">
                <div class="text-lg md:text-2xl font-bold font-mono text-neon">400+</div>
                <div class="text-[9px] md:text-xs text-gray-500 mt-0.5">Llamadas hechas</div>
            </div>
            <div class="bg-matrix-800 border border-matrix-600 rounded-xl p-2 md:p-3">
                <div class="text-lg md:text-2xl font-bold font-mono text-neon">&#x1F602;</div>
                <div class="text-[9px] md:text-xs text-gray-500 mt-0.5">Risas garantizadas</div>
            </div>
            <div class="bg-matrix-800 border border-matrix-600 rounded-xl p-2 md:p-3">
                <div class="text-lg md:text-2xl font-bold font-mono text-neon">3 min</div>
                <div class="text-[9px] md:text-xs text-gray-500 mt-0.5">Prueba gratis</div>
            </div>
        </div>

        <!-- Quick Presets (above form for fast conversion) -->
        <div class="w-full max-w-md mb-4" v-if="presets.length && callMode === 'prank'">
            <p class="text-xs text-gray-500 mb-2 text-center">&#x26A1; Elige una broma con un tap:</p>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                <button v-for="p in presets" :key="'quick-'+p.id" type="button"
                    @click="quickPreset(p)"
                    :class="['flex items-center gap-2 p-2.5 md:p-3 rounded-xl border text-left transition-all text-xs',
                        activePreset === p.id ? 'border-neon bg-neon/10 text-white shadow-[0_0_12px_rgba(57,255,20,0.2)]' : 'border-matrix-600 bg-matrix-800 text-gray-400 hover:border-neon/30 hover:text-gray-300']">
                    <span class="text-lg flex-shrink-0">{{ p.emoji }}</span>
                    <span class="truncate">{{ p.label }}</span>
                </button>
            </div>
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

            <!-- Call Type Toggle -->
            <div class="flex gap-2 mb-5 justify-center">
                <button type="button" @click="callMode = 'prank'"
                    :class="['px-4 py-2 rounded-full text-sm font-bold transition', callMode === 'prank' ? 'bg-neon text-matrix-900' : 'bg-matrix-700 text-gray-400']">
                    Broma con IA
                </button>
                <button type="button" @click="callMode = 'joke'"
                    :class="['px-4 py-2 rounded-full text-sm font-bold transition', callMode === 'joke' ? 'bg-neon text-matrix-900' : 'bg-matrix-700 text-gray-400']">
                    Chiste rapido
                </button>
            </div>

            <!-- JOKE MODE -->
            <form v-if="callMode === 'joke'" @submit.prevent="handleJoke">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-400 mb-2">Numero</label>
                    <div class="flex items-center bg-matrix-700 border border-matrix-600 rounded-xl overflow-hidden focus-within:border-neon/50 transition-colors">
                        <span class="px-3 py-3 text-gray-400 font-mono border-r border-matrix-600 text-sm">+52</span>
                        <input ref="jokePhoneInput" v-model="jokePhone" type="tel" maxlength="10" placeholder="55 1234 5678"
                            inputmode="numeric" autocomplete="tel-national"
                            class="flex-1 min-w-0 bg-transparent px-3 py-3 text-white font-mono outline-none placeholder:text-gray-600"
                            @input="jokePhone = $event.target.value.replace(/\D/g, '').slice(0, 10)"
                            @paste="onJokePhonePaste" />
                        <button type="button" @click="pickJokeContact"
                            title="Seleccionar de tus contactos"
                            class="shrink-0 px-3 py-3 border-l border-matrix-600 text-gray-400 hover:text-neon active:text-neon transition-colors min-h-[44px] flex items-center justify-center">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                        </button>
                    </div>
                    <p v-if="contactPickerHint" class="mt-1.5 text-[11px] text-gray-500">{{ contactPickerHint }}</p>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-400 mb-2">Idioma del chiste</label>
                    <div class="grid grid-cols-5 gap-2">
                        <button v-for="l in jokeLangs" :key="l.id" type="button" @click="jokeLang = l.id"
                            :class="['flex flex-col items-center p-2 rounded-xl border transition text-xs',
                                jokeLang === l.id ? 'border-neon bg-neon/10 text-white' : 'border-matrix-600 text-gray-500']">
                            <span class="text-lg">{{ l.flag }}</span>
                            <span>{{ l.label }}</span>
                        </button>
                    </div>
                </div>
                <button type="submit" :disabled="jokeLoading || jokePhone.length < 10"
                    class="w-full py-3.5 rounded-xl bg-neon text-matrix-900 font-bold text-base shadow-[var(--shadow-neon)] hover:shadow-[var(--shadow-neon-lg)] transition-all disabled:opacity-50">
                    {{ jokeLoading ? 'Llamando...' : 'Enviar chiste' }}
                </button>
                <p v-if="jokeError" class="mt-3 text-sm text-red-400 text-center">{{ jokeError }}</p>
                <div v-if="jokeSuccess" class="mt-3 p-3 rounded-xl bg-green-500/10 border border-green-500/20 text-center">
                    <p class="text-green-400 text-sm">Chiste enviado!</p>
                    <p class="text-xs text-gray-400 mt-1 italic">"{{ jokeSuccess }}"</p>
                </div>
                <p class="mt-4 text-[10px] text-gray-600 text-center">Un chiste aleatorio sera llamado al numero. Gratis!</p>
            </form>

            <!-- PRANK MODE -->
            <form v-else @submit.prevent="handleSubmit">
                <!-- Phone -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-400 mb-2">Numero que recibira la llamada</label>
                    <div class="flex items-center bg-matrix-700 border border-matrix-600 rounded-xl overflow-hidden focus-within:border-neon/50 transition-colors">
                        <span class="px-3 md:px-4 py-3 text-gray-400 font-mono border-r border-matrix-600 flex items-center gap-1.5 text-sm">
                            <span class="text-base">&#x1F1F2;&#x1F1FD;</span> +52
                        </span>
                        <input ref="phoneInput" v-model="phone" type="tel" maxlength="10" placeholder="55 1234 5678"
                            inputmode="numeric" autocomplete="tel-national"
                            class="flex-1 min-w-0 bg-transparent px-3 md:px-4 py-3 text-white font-mono text-base md:text-lg outline-none placeholder:text-gray-600"
                            @input="formatPhone" @paste="onPhonePaste" />
                        <button type="button" @click="pickContact"
                            title="Seleccionar de tus contactos"
                            class="shrink-0 px-3 md:px-4 py-3 border-l border-matrix-600 text-gray-400 hover:text-neon active:text-neon transition-colors min-h-[44px] flex items-center justify-center">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                        </button>
                    </div>
                    <p v-if="contactPickerHint" class="mt-1.5 text-[11px] text-gray-500">{{ contactPickerHint }}</p>
                    <p v-if="errors.phone" class="mt-2 text-sm text-red-400">{{ errors.phone }}</p>
                </div>

                <!-- Victim name -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-400 mb-2">Nombre de quien recibe la llamada</label>
                    <input v-model="victimName" placeholder="Ej: Juan, Maria, Sr. Lopez..."
                        class="w-full bg-matrix-700 border border-matrix-600 rounded-xl px-3 md:px-4 py-2.5 text-white text-sm outline-none focus:border-neon/50 transition-colors placeholder:text-gray-600" />
                    <p class="text-[10px] text-gray-500 mt-1">Opcional - la broma sera mucho mas realista con el nombre</p>
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

                <!-- Presets (compact, inside form as fallback) -->
                <div class="mb-5 md:hidden" v-if="presets.length && !activePreset">
                    <p class="text-xs text-gray-500 mb-2">O elige una idea arriba &#x2191;</p>
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

        <!-- Audio Demo -->
        <div class="w-full max-w-md mt-6 md:mt-8">
            <h3 class="text-sm font-medium text-gray-400 mb-3 text-center">&#x1F50A; Escucha una broma real</h3>
            <div class="bg-matrix-800 border border-matrix-600 rounded-xl p-4 md:p-5">
                <div class="text-xs text-gray-500 mb-3 italic">"Le llamaron de una pizzería diciendo que su pedido de 15 pizzas está listo..."</div>
                <audio ref="demoAudio" :src="demoAudioUrl" preload="none" class="w-full h-10 mb-3" controls controlslist="nodownload" />
                <div v-if="showTranscript" class="space-y-1.5 max-h-48 overflow-y-auto mt-3 pr-1 font-mono text-xs">
                    <div v-for="(line, i) in demoTranscript" :key="i" :class="line.role === 'ai' ? 'text-neon/80' : 'text-white/60'">
                        <span class="text-gray-600">{{ line.role === 'ai' ? 'IA' : 'Persona' }}:</span> {{ line.text }}
                    </div>
                </div>
                <button @click="showTranscript = !showTranscript" class="text-[10px] text-gray-500 hover:text-neon mt-2 transition">
                    {{ showTranscript ? 'Ocultar' : 'Ver' }} transcripcion
                </button>
            </div>
        </div>

        <!-- How it works -->
        <div class="w-full max-w-md mt-6 md:mt-8">
            <h3 class="text-sm font-medium text-gray-400 mb-3 text-center">Como funciona</h3>
            <div class="grid grid-cols-3 gap-3 text-center">
                <div class="p-3">
                    <div class="text-2xl mb-1">&#x270D;&#xFE0F;</div>
                    <div class="text-xs text-gray-300 font-medium">Describe la broma</div>
                    <div class="text-[10px] text-gray-600 mt-0.5">O elige una idea</div>
                </div>
                <div class="p-3">
                    <div class="text-2xl mb-1">&#x1F916;</div>
                    <div class="text-xs text-gray-300 font-medium">La IA llama</div>
                    <div class="text-[10px] text-gray-600 mt-0.5">Suena 100% humana</div>
                </div>
                <div class="p-3">
                    <div class="text-2xl mb-1">&#x1F923;</div>
                    <div class="text-xs text-gray-300 font-medium">Escucha y comparte</div>
                    <div class="text-[10px] text-gray-600 mt-0.5">Grabacion incluida</div>
                </div>
            </div>
        </div>

        </div>

        <AdBanner :slot="AD_SLOTS.homeBelowExample" format="leaderboard" class="mt-8" />

        <footer class="w-full mt-12 py-6 px-4 border-t border-matrix-700 text-center">
            <div class="flex items-center justify-center gap-4 text-xs text-gray-500">
                <router-link to="/terms" class="hover:text-neon transition">Términos</router-link>
                <span class="text-gray-700">·</span>
                <router-link to="/privacy" class="hover:text-neon transition">Privacidad</router-link>
                <span class="text-gray-700">·</span>
                <a href="mailto:soporte@vacilada.com" class="hover:text-neon transition">Soporte</a>
            </div>
            <p class="text-[10px] text-gray-600 mt-2">© 2026 Vacilada. Sólo para bromas consensuadas entre amigos.</p>
        </footer>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import AdBanner from '../components/AdBanner.vue';
import { AD_SLOTS } from '../adsense.js';
import axios from 'axios';

const router = useRouter();
const callMode = ref('prank');
const phone = ref('');
const victimName = ref('');
const fromAd = ref(false);
const demoAudio = ref(null);
const showTranscript = ref(false);
const demoAudioUrl = '/brand/demo-call.mp3';
const demoTranscript = [
    { role: 'ai', text: 'Bueno, buenas tardes. ¿Se encuentra Edith?' },
    { role: 'human', text: '¿De parte de quién?' },
    { role: 'ai', text: 'Soy Luis Ortiz, de la pizzería.' },
    { role: 'ai', text: 'Mire Edith, le llamo porque su pedido de 15 pizzas ya está listo. El total es de $3,500 pesos.' },
    { role: 'human', text: '¿¡Qué!?' },
    { role: 'ai', text: 'Sí, el repartidor ya va en camino. ¿Nos puede confirmar que hizo el pedido?' },
    { role: 'human', text: '¿Cuál?' },
    { role: 'ai', text: 'Mmm, ¿usted no pidió las pizzas? Porque el repartidor ya está en camino.' },
    { role: 'human', text: 'jaja' },
    { role: 'ai', text: 'Oiga, no es broma eh, son 15 pizzas y cuestan $3,500. ¿Seguro que no pidió?' },
    { role: 'human', text: 'No.' },
    { role: 'ai', text: 'Órale, lo mejor será que hablemos con el repartidor pa\' que no venga hasta allá.' },
    { role: 'human', text: '¡Sí, cancélalo!' },
    { role: 'ai', text: 'Va, le digo al repartidor que las regrese. ¡Que tenga buena tarde!' },
];

// Joke mode
const jokeLangs = [
    { id: 'es', flag: '\uD83C\uDDF2\uD83C\uDDFD', label: 'ES' },
    { id: 'en', flag: '\uD83C\uDDFA\uD83C\uDDF8', label: 'EN' },
    { id: 'pt', flag: '\uD83C\uDDE7\uD83C\uDDF7', label: 'PT' },
    { id: 'fr', flag: '\uD83C\uDDEB\uD83C\uDDF7', label: 'FR' },
    { id: 'de', flag: '\uD83C\uDDE9\uD83C\uDDEA', label: 'DE' },
];
const jokePhone = ref('');
const jokeLang = ref('es');
const jokeLoading = ref(false);
const jokeError = ref('');
const jokeSuccess = ref('');

async function handleJoke() {
    jokeError.value = ''; jokeSuccess.value = '';
    if (jokePhone.value.length < 10) { jokeError.value = 'Numero de 10 digitos'; return; }
    jokeLoading.value = true;
    try {
        const { data } = await axios.post('/trial-joke', {
            phone_number: jokePhone.value,
            language: jokeLang.value,
            source: 'trial',
        });
        const j = data.joke;
        jokeSuccess.value = j?.type === 'single' ? j.joke : `${j?.setup} ... ${j?.delivery}`;
    } catch (e) {
        jokeError.value = e.response?.data?.error || 'Error';
    } finally { jokeLoading.value = false; }
}
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

function getDeviceHash() {
    const raw = [
        navigator.userAgent,
        screen.width + 'x' + screen.height,
        Intl.DateTimeFormat().resolvedOptions().timeZone,
        navigator.language,
        navigator.hardwareConcurrency || '',
    ].join('|');
    let h = 0;
    for (let i = 0; i < raw.length; i++) { h = ((h << 5) - h + raw.charCodeAt(i)) | 0; }
    return Math.abs(h).toString(36);
}

onMounted(async () => {
    const params = new URLSearchParams(window.location.search);
    const urlRef = params.get('ref');
    if (urlRef) localStorage.setItem('vacilada_ref', urlRef.toUpperCase());
    fromAd.value = !!params.get('fbclid')
    const source = fromAd.value ? 'facebook_ad' : (urlRef ? 'referral' : 'direct')
    if (window.fbq) fbq('track', 'ViewContent', { content_name: 'Home', content_category: source })
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
    if (p.style) style.value = p.style;
    activePreset.value = p.id;
}

function quickPreset(p) {
    usePreset(p);
    callMode.value = 'prank';
    if (!style.value) {
        generating.value = true;
        axios.post('/api/generate-style', { scenario: p.scenario }).then(({ data }) => {
            if (data.style) style.value = data.style;
            if (data.voice) voice.value = data.voice;
        }).catch(() => { style.value = 'Casual y natural'; }).finally(() => { generating.value = false; });
    }
    setTimeout(() => {
        if (phoneInput.value) { phoneInput.value.focus(); phoneInput.value.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
    }, 100);
}

async function generateStyle() {
    if (!scenario.value.trim() || generating.value) return;
    generating.value = true;
    try {
        const { data } = await axios.post('/api/generate-style', { scenario: scenario.value.trim() });
        if (data.style) style.value = data.style;
        if (data.voice) voice.value = data.voice;
    } catch {} finally { generating.value = false; }
}

function formatPhone(e) { phone.value = e.target.value.replace(/\D/g, '').slice(0, 10); }

// Normalize any phone format to a Mexican 10-digit number, or null if not MX.
function normalizeMxPhone(raw) {
    if (!raw) return null;
    let d = String(raw).replace(/\D/g, '');
    if (d.startsWith('00')) d = d.slice(2);      // 00 international prefix
    if (d.startsWith('521') && d.length === 13) d = d.slice(3); // legacy +52 1 mobile
    else if (d.startsWith('52') && d.length === 12) d = d.slice(2);
    if (d.length === 10 && /^[1-9]/.test(d)) return d;
    return null;
}

function onPhonePaste(e) {
    const pasted = (e.clipboardData || window.clipboardData)?.getData('text') || '';
    const normalized = normalizeMxPhone(pasted);
    if (normalized) {
        e.preventDefault();
        phone.value = normalized;
    }
}

const phoneInput = ref(null);
const jokePhoneInput = ref(null);

function onJokePhonePaste(e) {
    const pasted = (e.clipboardData || window.clipboardData)?.getData('text') || '';
    const normalized = normalizeMxPhone(pasted);
    if (normalized) { e.preventDefault(); jokePhone.value = normalized; }
}

async function pickJokeContact() {
    if (contactPickerSupported) {
        try {
            const picked = await navigator.contacts.select(['tel'], { multiple: false });
            if (!picked || !picked.length) return;
            const tels = picked[0].tel || [];
            let normalized = null;
            for (const t of tels) { normalized = normalizeMxPhone(t); if (normalized) break; }
            if (!normalized) { alert('Solo se aceptan números de México (+52).'); return; }
            jokePhone.value = normalized;
        } catch (err) {
            if (err?.name !== 'AbortError') alert('No se pudo acceder a contactos.');
        }
        return;
    }
    if (jokePhoneInput.value) jokePhoneInput.value.focus();
}
const contactPickerSupported = typeof navigator !== 'undefined' && 'contacts' in navigator && 'ContactsManager' in window;
const isIOS = typeof navigator !== 'undefined' && /iPad|iPhone|iPod/.test(navigator.userAgent);
const contactPickerHint = isIOS
    ? 'Toca el campo y elige un contacto del teclado.'
    : (contactPickerSupported ? '' : 'Pega o escribe el número (10 dígitos).');

async function pickContact() {
    errors.phone = '';
    if (contactPickerSupported) {
        try {
            const picked = await navigator.contacts.select(['tel'], { multiple: false });
            if (!picked || !picked.length) return;
            const tels = picked[0].tel || [];
            let normalized = null;
            for (const t of tels) {
                normalized = normalizeMxPhone(t);
                if (normalized) break;
            }
            if (!normalized) {
                errors.phone = 'Solo se aceptan números de México (+52).';
                return;
            }
            phone.value = normalized;
        } catch (err) {
            if (err?.name !== 'AbortError') {
                errors.phone = 'No se pudo acceder a contactos.';
            }
        }
        return;
    }
    // Fallback: focus the input so the device's native contact autofill/keyboard surfaces.
    if (phoneInput.value) {
        phoneInput.value.focus();
        if (isIOS) {
            errors.phone = 'En iPhone: toca las sugerencias arriba del teclado para elegir un contacto.';
        } else {
            errors.phone = 'Esta función solo funciona en Chrome de Android. Escribe el número manualmente.';
        }
    }
}

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
        const payload = {
            phone_number: digits,
            scenario: scenario.value.trim(),
            character: style.value,
            voice: voice.value,
            victim_name: victimName.value.trim(),
        };
        if (!user.value) payload.device_hash = getDeviceHash();
        const { data } = await axios.post(endpoint, payload);
        if (window.fbq) fbq('trackCustom', 'TrialCallStarted', {
          source: user.value ? 'paid' : 'trial',
          from_ad: !!new URLSearchParams(window.location.search).get('fbclid'),
        })
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
