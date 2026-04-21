<template>
  <div class="max-w-6xl mx-auto px-4 py-8 space-y-6">
    <h1 class="text-3xl font-bold text-white">Crecimiento Viral</h1>

    <div v-if="me" class="bg-matrix-800 border border-neon/30 rounded-xl p-5">
      <div class="flex justify-between items-center mb-3">
        <h2 class="text-sm font-semibold text-neon uppercase">Tu link personal</h2>
        <span class="text-xs text-gray-500">Código: <strong class="font-mono text-neon">{{ me.code }}</strong></span>
      </div>
      <div class="flex gap-2 items-center mb-4">
        <input :value="me.link" readonly class="flex-1 bg-matrix-900 border border-matrix-600 rounded-lg px-3 py-2 text-xs font-mono text-gray-200" />
        <button @click="copyLink" class="px-4 py-2 rounded-lg bg-neon text-matrix-900 font-semibold text-xs hover:shadow-neon transition">{{ copied ? '✓' : 'Copiar' }}</button>
      </div>
      <div class="grid grid-cols-2 md:grid-cols-5 gap-2">
        <div class="bg-matrix-900 rounded-lg p-3 text-center">
          <div class="text-2xl font-bold text-neon">{{ me.clicks }}</div>
          <div class="text-[10px] text-gray-500 uppercase mt-1">Clicks totales</div>
        </div>
        <div class="bg-matrix-900 rounded-lg p-3 text-center">
          <div class="text-2xl font-bold text-white">{{ me.unique_visitors }}</div>
          <div class="text-[10px] text-gray-500 uppercase mt-1">Únicos</div>
        </div>
        <div class="bg-matrix-900 rounded-lg p-3 text-center">
          <div class="text-2xl font-bold text-white">{{ me.signups }}</div>
          <div class="text-[10px] text-gray-500 uppercase mt-1">Se registraron</div>
        </div>
        <div class="bg-matrix-900 rounded-lg p-3 text-center">
          <div class="text-2xl font-bold text-neon">{{ me.converted }}</div>
          <div class="text-[10px] text-gray-500 uppercase mt-1">Convertidos (+2 c/u)</div>
        </div>
        <div class="bg-matrix-900 rounded-lg p-3 text-center">
          <div class="text-2xl font-bold text-white">{{ me.conversion_rate }}%</div>
          <div class="text-[10px] text-gray-500 uppercase mt-1">Tasa registro</div>
        </div>
      </div>
    </div>

    <div v-if="stats" class="grid grid-cols-2 md:grid-cols-4 gap-3">
      <div class="bg-matrix-800 border border-matrix-600 rounded-xl p-4">
        <div class="text-3xl font-bold text-neon">{{ stats.k_factor }}</div>
        <div class="text-xs text-gray-500 uppercase mt-1">K-factor</div>
        <div class="text-[10px] text-gray-600 mt-1">Meta: &gt; 1.0</div>
      </div>
      <div class="bg-matrix-800 border border-matrix-600 rounded-xl p-4">
        <div class="text-3xl font-bold text-neon">{{ stats.active_users }}</div>
        <div class="text-xs text-gray-500 uppercase mt-1">Usuarios activos</div>
      </div>
      <div class="bg-matrix-800 border border-matrix-600 rounded-xl p-4">
        <div class="text-3xl font-bold text-neon">{{ stats.with_referrer }}</div>
        <div class="text-xs text-gray-500 uppercase mt-1">Referidos (total)</div>
      </div>
      <div class="bg-matrix-800 border border-matrix-600 rounded-xl p-4">
        <div class="text-3xl font-bold text-neon">{{ stats.avg_cycle_days ?? '—' }}</div>
        <div class="text-xs text-gray-500 uppercase mt-1">Cycle time (días)</div>
        <div class="text-[10px] text-gray-600 mt-1">Meta: &lt; 7</div>
      </div>
    </div>

    <div v-if="viral" class="grid md:grid-cols-2 gap-4">
      <div class="bg-matrix-800 border border-matrix-600 rounded-xl p-5">
        <h2 class="text-sm font-semibold text-gray-400 uppercase mb-3">Share funnel</h2>
        <div class="space-y-2 text-sm">
          <div class="flex justify-between"><span class="text-gray-400">Llamadas totales</span><span class="text-white font-semibold">{{ viral.share_funnel.total_calls }}</span></div>
          <div class="flex justify-between"><span class="text-gray-400">Llamadas públicas</span><span class="text-white font-semibold">{{ viral.share_funnel.public_calls }}</span></div>
          <div class="flex justify-between"><span class="text-gray-400">Llamadas con vistas</span><span class="text-white font-semibold">{{ viral.share_funnel.calls_with_views }}</span></div>
          <div class="flex justify-between"><span class="text-gray-400">Vistas totales</span><span class="text-neon font-bold">{{ viral.share_funnel.total_share_views }}</span></div>
          <div class="flex justify-between"><span class="text-gray-400">Share rate</span><span class="text-neon font-bold">{{ viral.share_funnel.share_rate }}%</span></div>
        </div>
        <div class="text-xs text-gray-500 mt-3">Meta: share rate &gt; 40%</div>
      </div>

      <div class="bg-matrix-800 border border-matrix-600 rounded-xl p-5">
        <h2 class="text-sm font-semibold text-gray-400 uppercase mb-3">Tráfico por canal</h2>
        <div v-if="viral.channels.length" class="space-y-2 text-sm">
          <div v-for="c in viral.channels" :key="c.utm_source" class="flex justify-between">
            <span class="text-gray-400">{{ c.utm_source }}</span>
            <span class="text-white"><strong class="text-neon">{{ c.touches }}</strong> touches · {{ c.users }} users</span>
          </div>
        </div>
        <div v-else class="text-gray-500 text-sm">Sin data aún. Comparte tu link con UTMs para empezar.</div>
      </div>
    </div>

    <div v-if="viral?.whatsapp_ab?.length" class="bg-matrix-800 border border-matrix-600 rounded-xl p-5">
      <h2 class="text-sm font-semibold text-gray-400 uppercase mb-3">A/B test — mensaje WhatsApp</h2>
      <table class="w-full text-sm">
        <thead class="text-xs text-gray-500 uppercase">
          <tr><th class="text-left py-2">Variante</th><th class="text-right py-2">Vistas</th><th class="text-right py-2">Clicks</th><th class="text-right py-2">CTR</th></tr>
        </thead>
        <tbody>
          <tr v-for="v in viral.whatsapp_ab" :key="v.variant" class="border-t border-matrix-700">
            <td class="py-2 font-semibold text-neon">{{ v.variant }}</td>
            <td class="py-2 text-right">{{ v.impressions }}</td>
            <td class="py-2 text-right">{{ v.clicks }}</td>
            <td class="py-2 text-right font-bold">{{ v.ctr }}%</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="bg-matrix-800 border border-matrix-600 rounded-xl p-4">
      <h2 class="text-lg font-bold text-white mb-3">Top referidores</h2>
      <div v-if="top.length" class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="text-xs text-gray-500 uppercase">
            <tr>
              <th class="text-left py-2">#</th>
              <th class="text-left py-2">Usuario</th>
              <th class="text-left py-2">Código</th>
              <th class="text-right py-2">Invitados</th>
              <th class="text-right py-2">Convertidos</th>
              <th class="text-right py-2">Créditos</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(u, i) in top" :key="u.id" class="border-t border-matrix-700">
              <td class="py-2 text-gray-500">{{ i + 1 }}</td>
              <td class="py-2 text-white">{{ u.name }}<br><span class="text-xs text-gray-500">{{ u.email }}</span></td>
              <td class="py-2 text-neon font-mono">{{ u.referral_code }}</td>
              <td class="py-2 text-right">{{ u.referred_count }}</td>
              <td class="py-2 text-right">{{ u.converted_count }}</td>
              <td class="py-2 text-right text-neon font-bold">{{ u.converted_count * 2 }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <p v-else class="text-gray-500 text-sm py-4 text-center">Aún no hay referidos.</p>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';

const stats = ref(null);
const top = ref([]);
const viral = ref(null);
const me = ref(null);
const copied = ref(false);

function copyLink() {
  if (!me.value || !navigator.clipboard) return;
  navigator.clipboard.writeText(me.value.link);
  copied.value = true;
  setTimeout(() => { copied.value = false; }, 2000);
}

onMounted(async () => {
  try {
    const [{ data: r }, { data: v }] = await Promise.all([
      axios.get('/admin-api/referrals'),
      axios.get('/admin-api/viral-metrics'),
    ]);
    stats.value = r.stats;
    top.value = r.top || [];
    me.value = r.me || null;
    viral.value = v;
  } catch (e) {
    console.error(e);
  }
});
</script>
