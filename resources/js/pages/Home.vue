<template>
    <div class="min-h-screen flex flex-col items-center justify-center px-4 py-12">
        <!-- Hero -->
        <div class="text-center mb-12">
            <div class="text-7xl mb-6 animate-[ring_1s_ease-in-out_infinite]">
                &#x1F4DE;
            </div>
            <h1 class="text-4xl md:text-6xl font-bold font-mono text-neon animate-[glow_1.5s_ease-in-out_infinite_alternate] mb-4">
                ECHJokes
            </h1>
            <p class="text-xl md:text-2xl text-gray-400 max-w-lg mx-auto">
                Bromas telefonicas con IA. Tu describes la situacion, la IA hace la llamada.
            </p>
        </div>

        <!-- Form -->
        <div class="w-full max-w-md bg-matrix-800 border border-matrix-600 rounded-2xl p-8 shadow-lg">
            <form @submit.prevent="handleSubmit">
                <!-- Gift Toggle -->
                <div class="flex items-center justify-center gap-2 mb-6">
                    <button
                        type="button"
                        :class="['px-4 py-2 rounded-full text-sm font-medium transition-all', !isGift ? 'bg-neon text-matrix-900' : 'bg-matrix-700 text-gray-400']"
                        @click="isGift = false"
                    >
                        A mi numero
                    </button>
                    <button
                        type="button"
                        :class="['px-4 py-2 rounded-full text-sm font-medium transition-all', isGift ? 'bg-neon text-matrix-900' : 'bg-matrix-700 text-gray-400']"
                        @click="isGift = true"
                    >
                        &#x1F3AF; A un amigo
                    </button>
                </div>

                <!-- Phone Input -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-400 mb-2">
                        {{ isGift ? 'Tu numero (para confirmar)' : 'Numero que recibira la llamada' }}
                    </label>
                    <div class="flex items-center bg-matrix-700 border border-matrix-600 rounded-xl overflow-hidden focus-within:border-neon/50 transition-colors">
                        <span class="px-4 py-3 text-gray-400 font-mono border-r border-matrix-600 flex items-center gap-2">
                            <span class="text-lg">&#x1F1F2;&#x1F1FD;</span> +52
                        </span>
                        <input
                            v-model="phone"
                            type="tel"
                            maxlength="10"
                            placeholder="55 1234 5678"
                            class="flex-1 bg-transparent px-4 py-3 text-white font-mono text-lg outline-none placeholder:text-gray-600"
                            @input="formatPhone"
                        />
                    </div>
                    <p v-if="errors.phone" class="mt-2 text-sm text-red-400">{{ errors.phone }}</p>
                </div>

                <!-- Gift: Recipient phone -->
                <template v-if="isGift">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-400 mb-2">Numero de tu amigo (el que recibe la broma)</label>
                        <div class="flex items-center bg-matrix-700 border border-matrix-600 rounded-xl overflow-hidden focus-within:border-neon/50 transition-colors">
                            <span class="px-4 py-3 text-gray-400 font-mono border-r border-matrix-600">+52</span>
                            <input
                                v-model="recipientPhone"
                                type="tel"
                                maxlength="10"
                                placeholder="55 9876 5432"
                                class="flex-1 bg-transparent px-4 py-3 text-white font-mono text-lg outline-none placeholder:text-gray-600"
                                @input="recipientPhone = $event.target.value.replace(/\D/g, '').slice(0, 10)"
                            />
                        </div>
                        <p v-if="errors.recipient_phone" class="mt-2 text-sm text-red-400">{{ errors.recipient_phone }}</p>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-400 mb-2">Tu nombre</label>
                        <input
                            v-model="senderName"
                            type="text"
                            maxlength="50"
                            placeholder="Tu nombre"
                            class="w-full bg-matrix-700 border border-matrix-600 rounded-xl px-4 py-3 text-white outline-none focus:border-neon/50 transition-colors"
                        />
                    </div>
                </template>

                <!-- SCENARIO — The core input -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-400 mb-2">
                        &#x1F3AD; Describe la broma
                    </label>
                    <textarea
                        v-model="scenario"
                        maxlength="500"
                        rows="4"
                        placeholder="Ej: 'Que la lavadora hace mucho ruido y los vecinos se quejan, que llame como si fuera de la administracion del condominio'

'Que le hablen del banco diciendo que su tarjeta tiene un cargo sospechoso de $50,000 en una tienda de peluches'

'Que le hablen de una veterinaria diciendo que su perro gano un concurso de belleza'"
                        class="w-full bg-matrix-700 border border-matrix-600 rounded-xl px-4 py-3 text-white text-sm outline-none focus:border-neon/50 transition-colors resize-none placeholder:text-gray-600 leading-relaxed"
                    ></textarea>
                    <div class="flex justify-between mt-1">
                        <p class="text-xs text-gray-600">La IA creara un personaje y hara la llamada</p>
                        <p class="text-xs" :class="scenario.length > 450 ? 'text-yellow-500' : 'text-gray-600'">{{ scenario.length }}/500</p>
                    </div>
                    <p v-if="errors.scenario" class="mt-1 text-sm text-red-400">{{ errors.scenario }}</p>
                </div>

                <!-- Quick scenario suggestions -->
                <div class="mb-6">
                    <p class="text-xs text-gray-500 mb-2">Ideas rapidas:</p>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="idea in quickIdeas"
                            :key="idea.label"
                            type="button"
                            class="px-3 py-1 rounded-full text-xs bg-matrix-700 text-gray-400 border border-matrix-600 hover:border-neon/30 hover:text-neon transition-all"
                            @click="scenario = idea.text"
                        >
                            {{ idea.label }}
                        </button>
                    </div>
                </div>

                <!-- Delivery Type -->
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-400 mb-2">Tipo de entrega</label>
                    <div class="flex gap-2">
                        <button
                            type="button"
                            :class="['flex-1 px-4 py-2 rounded-xl text-sm font-medium transition-all', deliveryType === 'call' ? 'bg-neon text-matrix-900' : 'bg-matrix-700 text-gray-400 border border-matrix-600']"
                            @click="deliveryType = 'call'"
                        >
                            &#x1F4DE; Llamada con IA
                        </button>
                        <button
                            type="button"
                            :class="['flex-1 px-4 py-2 rounded-xl text-sm font-medium transition-all', deliveryType === 'whatsapp' ? 'bg-neon text-matrix-900' : 'bg-matrix-700 text-gray-400 border border-matrix-600']"
                            @click="deliveryType = 'whatsapp'"
                        >
                            &#x1F4AC; WhatsApp
                        </button>
                    </div>
                    <p class="text-xs text-gray-600 mt-1">
                        {{ deliveryType === 'call' ? 'La IA llama, contesta y conversa en tiempo real' : 'Se envia un mensaje de broma por WhatsApp' }}
                    </p>
                </div>

                <!-- Submit -->
                <button
                    type="submit"
                    :disabled="loading"
                    class="w-full py-4 rounded-xl bg-neon text-matrix-900 font-bold text-lg shadow-[var(--shadow-neon)] hover:shadow-[var(--shadow-neon-lg)] transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    <span v-if="loading" class="flex items-center justify-center gap-2">
                        <svg class="animate-spin h-5 w-5" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" />
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                        </svg>
                        Procesando...
                    </span>
                    <span v-else>
                        {{ deliveryType === 'whatsapp' ? '&#x1F4AC; Enviar broma por WhatsApp!' : '&#x1F4DE; Hacer la llamada de broma!' }}
                    </span>
                </button>

                <p v-if="errors.general" class="mt-4 text-sm text-red-400 text-center">{{ errors.general }}</p>
            </form>

            <p class="mt-6 text-xs text-gray-600 text-center">
                Al proporcionar el numero aceptas que ECHJokes realice una {{ deliveryType === 'whatsapp' ? 'mensaje' : 'llamada' }} de broma.
            </p>
        </div>

        <!-- Example Transcript -->
        <div class="w-full max-w-md mt-12">
            <h3 class="text-sm font-medium text-gray-500 mb-4 text-center">Ejemplo: "La lavadora hace mucho ruido"</h3>
            <div class="bg-matrix-800 border border-matrix-600 rounded-xl p-6 font-mono text-sm space-y-3">
                <div class="text-gray-500 text-xs italic mb-2">La IA llama pretendiendo ser de la administracion...</div>
                <div class="text-neon/80"><span class="text-gray-500">IA:</span> Buenas tardes, le hablo de la administracion del condominio. Tenemos un reporte de ruido excesivo proveniente de su departamento...</div>
                <div class="text-white/60"><span class="text-gray-500">Persona:</span> Ah si? Que tipo de ruido?</div>
                <div class="text-neon/80"><span class="text-gray-500">IA:</span> Pues mire, nos reportaron que suena como si tuviera un helicoptero estacionado en su cocina. Los vecinos del 4B dicen que no pueden ni ver su telenovela...</div>
                <div class="text-white/60"><span class="text-gray-500">Persona:</span> Jajaja es la lavadora!</div>
                <div class="text-neon/80"><span class="text-gray-500">IA:</span> Ah, es una lavadora? Bueno, entonces necesitamos que le ponga un silenciador. O minimo que le ponga musica bonita para que los vecinos disfruten el show...</div>
                <div class="text-neon/60"><span class="text-gray-500">IA:</span> ...esto fue una broma de ECHJokes! Hasta luego!</div>
            </div>
        </div>

        <!-- Nav links -->
        <div class="mt-8 flex gap-6 text-sm">
            <router-link to="/pricing" class="text-gray-500 hover:text-neon transition-colors">Precios</router-link>
            <router-link to="/login" class="text-gray-500 hover:text-neon transition-colors">Mi cuenta</router-link>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive } from 'vue';
import axios from 'axios';

const phone = ref('');
const scenario = ref('');
const deliveryType = ref('call');
const isGift = ref(false);
const recipientPhone = ref('');
const senderName = ref('');
const loading = ref(false);
const errors = reactive({ phone: '', recipient_phone: '', scenario: '', general: '' });

const quickIdeas = [
    { label: '&#x1F9FA; Lavadora ruidosa', text: 'Que llamen de la administracion del condominio diciendo que la lavadora hace demasiado ruido y que los vecinos se estan quejando, que si no le baja van a tener que cobrarle una multa' },
    { label: '&#x1F4B3; Cargo sospechoso', text: 'Que llamen del banco diciendo que detectaron un cargo sospechoso de $47,000 pesos en una tienda de disfraces para mascotas y que necesitan confirmar si fue el' },
    { label: '&#x1F436; Concurso de perros', text: 'Que llamen de una veterinaria diciendo que su perro fue nominado para un concurso de belleza canina y necesitan confirmar su asistencia para la pasarela' },
    { label: '&#x1F3C6; Premio falso', text: 'Que llamen diciendo que gano un viaje todo incluido a Cancun pero que para reclamarlo necesita contestar 3 preguntas de cultura general' },
    { label: '&#x1F355; Pedido equivocado', text: 'Que llamen de una pizzeria diciendo que su pedido de 15 pizzas esta listo para entregar y que el total son $3,500 pesos' },
];

function formatPhone(e) {
    phone.value = e.target.value.replace(/\D/g, '').slice(0, 10);
}

async function handleSubmit() {
    errors.phone = '';
    errors.recipient_phone = '';
    errors.scenario = '';
    errors.general = '';

    const digits = phone.value.replace(/\D/g, '');
    if (digits.length !== 10) { errors.phone = 'El numero debe tener 10 digitos.'; return; }
    if (digits[0] === '0') { errors.phone = 'El numero no puede empezar con 0.'; return; }

    if (!scenario.value.trim() || scenario.value.trim().length < 10) {
        errors.scenario = 'Describe la situacion para la broma (minimo 10 caracteres).';
        return;
    }

    if (isGift.value) {
        const rd = recipientPhone.value.replace(/\D/g, '');
        if (rd.length !== 10) { errors.recipient_phone = 'El numero debe tener 10 digitos.'; return; }
        if (!senderName.value.trim()) { errors.general = 'Indica tu nombre.'; return; }
    }

    loading.value = true;

    try {
        const payload = {
            phone_number: digits,
            scenario: scenario.value.trim(),
            delivery_type: deliveryType.value,
            is_gift: isGift.value,
        };

        if (isGift.value) {
            payload.recipient_phone = recipientPhone.value.replace(/\D/g, '');
            payload.sender_name = senderName.value.trim();
        }

        const { data } = await axios.post('/checkout', payload);
        window.location.href = data.checkout_url;
    } catch (err) {
        if (err.response?.status === 422) {
            const se = err.response.data.errors;
            errors.phone = se?.phone_number?.[0] || '';
            errors.recipient_phone = se?.recipient_phone?.[0] || '';
            errors.scenario = se?.scenario?.[0] || '';
            errors.general = se?.delivery_type?.[0] || '';
        } else {
            errors.general = 'Algo salio mal. Intentalo de nuevo.';
        }
        loading.value = false;
    }
}
</script>
