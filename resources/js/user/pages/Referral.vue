<template>
  <div class="max-w-2xl mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold font-mono text-neon mb-2">Invita y Gana</h1>
    <p class="text-gray-400 mb-6">Comparte tu link. Cuando un amigo se registre y haga su primera broma, ambos reciben <strong class="text-neon">2 bromas gratis</strong>.</p>

    <div v-if="data" class="space-y-5">
      <div class="bg-matrix-800 border border-matrix-600 rounded-xl p-5">
        <div class="text-xs text-gray-500 uppercase tracking-wide mb-2">Tu link personal</div>
        <div class="flex gap-2 items-center">
          <input :value="data.link" readonly class="flex-1 bg-matrix-900 border border-matrix-600 rounded-lg px-3 py-2 text-sm font-mono" />
          <button @click="copy" class="px-4 py-2 rounded-lg bg-neon text-matrix-900 font-semibold text-sm hover:shadow-[var(--shadow-neon)] transition">{{ copied ? 'Copiado!' : 'Copiar' }}</button>
        </div>
      </div>

      <div class="grid grid-cols-3 gap-3">
        <a :href="whatsappUrl" target="_blank" rel="noopener" class="flex flex-col items-center gap-2 bg-[#25D366] hover:bg-[#1ebe5a] text-white rounded-xl p-4 transition">
          <span class="text-2xl">&#x1F4AC;</span>
          <span class="text-xs font-semibold">WhatsApp</span>
        </a>
        <button @click="shareNative" class="flex flex-col items-center gap-2 bg-matrix-700 hover:bg-matrix-600 text-white rounded-xl p-4 transition border border-matrix-600">
          <span class="text-2xl">&#x1F517;</span>
          <span class="text-xs font-semibold">Compartir</span>
        </button>
        <button @click="copy" class="flex flex-col items-center gap-2 bg-matrix-700 hover:bg-matrix-600 text-white rounded-xl p-4 transition border border-matrix-600">
          <span class="text-2xl">&#x1F4CB;</span>
          <span class="text-xs font-semibold">{{ copied ? 'Copiado!' : 'Copiar' }}</span>
        </button>
      </div>

      <div class="grid grid-cols-3 gap-3">
        <div class="bg-matrix-800 border border-matrix-600 rounded-xl p-4 text-center">
          <div class="text-3xl font-bold text-neon">{{ data.referred_total }}</div>
          <div class="text-xs text-gray-500 uppercase mt-1">Invitados</div>
        </div>
        <div class="bg-matrix-800 border border-matrix-600 rounded-xl p-4 text-center">
          <div class="text-3xl font-bold text-neon">{{ data.referred_successful }}</div>
          <div class="text-xs text-gray-500 uppercase mt-1">Convertidos</div>
        </div>
        <div class="bg-matrix-800 border border-matrix-600 rounded-xl p-4 text-center">
          <div class="text-3xl font-bold text-neon">{{ data.credits_earned }}</div>
          <div class="text-xs text-gray-500 uppercase mt-1">Bromas ganadas</div>
        </div>
      </div>

      <AdBanner :slot="AD_SLOTS.referralBelowStats" format="leaderboard" />

      <div class="bg-matrix-800 border border-matrix-600 rounded-xl p-5">
        <h3 class="text-sm font-semibold mb-2">&#x1F4A1; Como funciona</h3>
        <ol class="text-sm text-gray-400 space-y-1 list-decimal pl-5">
          <li>Comparte tu link por WhatsApp, TikTok, o donde quieras.</li>
          <li>Tu amigo se registra con tu link.</li>
          <li>Cuando haga su primera broma, los dos reciben 2 bromas gratis.</li>
        </ol>
      </div>
    </div>

    <div v-else-if="loading" class="text-gray-500 text-center py-8">Cargando...</div>
    <div v-else class="text-gray-500 text-center py-8">Inicia sesion para ver tu link.</div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import AdBanner from '../../components/AdBanner.vue';
import { AD_SLOTS } from '../../adsense.js';

const data = ref(null);
const loading = ref(true);
const copied = ref(false);

const whatsappUrl = computed(() => data.value ? `https://wa.me/?text=${encodeURIComponent(data.value.share_text)}` : '#');

function copy() {
  if (!data.value || !navigator.clipboard) return;
  navigator.clipboard.writeText(data.value.link);
  copied.value = true;
  setTimeout(() => { copied.value = false; }, 2000);
}

async function shareNative() {
  if (!data.value) return;
  if (navigator.share) {
    try { await navigator.share({ title: 'Vacilada', text: data.value.share_text, url: data.value.link }); } catch (e) {}
  } else {
    copy();
  }
}

onMounted(async () => {
  try {
    const { data: r } = await axios.get('/api/referrals/me');
    data.value = r;
  } catch (e) {
    data.value = null;
  } finally {
    loading.value = false;
  }
});
</script>
