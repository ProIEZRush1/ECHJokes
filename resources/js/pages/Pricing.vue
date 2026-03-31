<template>
    <div class="min-h-screen flex flex-col items-center px-4 py-16">
        <h1 class="text-4xl font-bold font-mono text-neon mb-4">Precios ECHJokes</h1>
        <p class="text-gray-400 mb-12 text-center max-w-md">
            Bromas telefonicas con IA. La IA llama, conversa y hace reir.
        </p>

        <div class="grid md:grid-cols-3 gap-6 w-full max-w-4xl">
            <div class="bg-matrix-800 border border-matrix-600 rounded-2xl p-8 text-center">
                <h3 class="text-lg font-medium text-gray-300 mb-2">Por broma</h3>
                <div class="text-4xl font-bold text-white mb-1">$35</div>
                <div class="text-sm text-gray-500 mb-6">MXN / broma</div>
                <ul class="text-sm text-gray-400 space-y-2 mb-8 text-left">
                    <li>&#x2713; Llamada con IA en tiempo real</li>
                    <li>&#x2713; Tu escribes el escenario</li>
                    <li>&#x2713; Grabacion de la llamada</li>
                </ul>
                <router-link to="/" class="block w-full py-3 rounded-xl bg-matrix-700 text-white font-medium hover:bg-matrix-600 transition-colors text-center">
                    Hacer una broma
                </router-link>
            </div>

            <div class="bg-matrix-800 border-2 border-neon rounded-2xl p-8 text-center relative shadow-[var(--shadow-neon)]">
                <div class="absolute -top-3 left-1/2 -translate-x-1/2 bg-neon text-matrix-900 text-xs font-bold px-4 py-1 rounded-full">POPULAR</div>
                <h3 class="text-lg font-medium text-neon mb-2">Bromista</h3>
                <div class="text-4xl font-bold text-white mb-1">$99</div>
                <div class="text-sm text-gray-500 mb-6">MXN / mes</div>
                <ul class="text-sm text-gray-400 space-y-2 mb-8 text-left">
                    <li>&#x2713; 5 bromas al mes</li>
                    <li>&#x2713; Llamadas con IA</li>
                    <li>&#x2713; Grabaciones</li>
                    <li>&#x2713; Bromas por WhatsApp</li>
                </ul>
                <button @click="subscribe('bromista')" class="block w-full py-3 rounded-xl bg-neon text-matrix-900 font-bold hover:shadow-[var(--shadow-neon-lg)] transition-all">Suscribirme</button>
            </div>

            <div class="bg-matrix-800 border border-matrix-600 rounded-2xl p-8 text-center">
                <h3 class="text-lg font-medium text-gray-300 mb-2">Comediante</h3>
                <div class="text-4xl font-bold text-white mb-1">$299</div>
                <div class="text-sm text-gray-500 mb-6">MXN / mes</div>
                <ul class="text-sm text-gray-400 space-y-2 mb-8 text-left">
                    <li>&#x2713; 20 bromas al mes</li>
                    <li>&#x2713; Todo lo del plan Bromista</li>
                    <li>&#x2713; Soporte prioritario</li>
                </ul>
                <button @click="subscribe('comediante')" class="block w-full py-3 rounded-xl bg-matrix-700 text-white font-medium hover:bg-matrix-600 transition-colors">Suscribirme</button>
            </div>
        </div>
    </div>
</template>

<script setup>
function subscribe(plan) { alert('Proximamente! Por ahora usa el pago por broma.'); }
</script>
