<template>
  <div class="max-w-5xl mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold text-white mb-2">Referrals</h1>
    <p class="text-gray-400 mb-6">Todos los usuarios que han referido o sido referidos.</p>

    <div v-if="stats" class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
      <div class="bg-matrix-800 border border-matrix-600 rounded-xl p-4">
        <div class="text-3xl font-bold text-neon">{{ stats.total_users }}</div>
        <div class="text-xs text-gray-500 uppercase mt-1">Usuarios totales</div>
      </div>
      <div class="bg-matrix-800 border border-matrix-600 rounded-xl p-4">
        <div class="text-3xl font-bold text-neon">{{ stats.with_referrer }}</div>
        <div class="text-xs text-gray-500 uppercase mt-1">Con referidor</div>
      </div>
      <div class="bg-matrix-800 border border-matrix-600 rounded-xl p-4">
        <div class="text-3xl font-bold text-neon">{{ stats.credited }}</div>
        <div class="text-xs text-gray-500 uppercase mt-1">Convertidos (+2 c/u)</div>
      </div>
      <div class="bg-matrix-800 border border-matrix-600 rounded-xl p-4">
        <div class="text-3xl font-bold text-neon">{{ stats.credits_given }}</div>
        <div class="text-xs text-gray-500 uppercase mt-1">Créditos regalados</div>
      </div>
    </div>

    <div class="bg-matrix-800 border border-matrix-600 rounded-xl p-4">
      <h2 class="text-lg font-bold text-white mb-3">Top referidores</h2>
      <div v-if="top.length" class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="text-xs text-gray-500 uppercase">
            <tr>
              <th class="text-left py-2">#</th>
              <th class="text-left py-2">Nombre</th>
              <th class="text-left py-2">Email</th>
              <th class="text-left py-2">Código</th>
              <th class="text-right py-2">Invitados</th>
              <th class="text-right py-2">Convertidos</th>
              <th class="text-right py-2">Créditos ganados</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(u, i) in top" :key="u.id" class="border-t border-matrix-700">
              <td class="py-3 text-gray-500">{{ i + 1 }}</td>
              <td class="py-3 text-white">{{ u.name }}</td>
              <td class="py-3 text-gray-400">{{ u.email }}</td>
              <td class="py-3 text-neon font-mono">{{ u.referral_code }}</td>
              <td class="py-3 text-right">{{ u.referred_count }}</td>
              <td class="py-3 text-right">{{ u.converted_count }}</td>
              <td class="py-3 text-right text-neon font-bold">{{ u.converted_count * 2 }}</td>
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

onMounted(async () => {
  try {
    const { data } = await axios.get('/admin-api/referrals');
    stats.value = data.stats;
    top.value = data.top || [];
  } catch (e) {
    console.error(e);
  }
});
</script>
