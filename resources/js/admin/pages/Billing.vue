<template>
  <div class="p-6 space-y-6">
    <h1 class="text-2xl font-bold font-mono">Billing & Usage</h1>

    <div v-if="loading" class="text-gray-500 text-center py-12">Loading...</div>

    <template v-else>
      <!-- API Balances -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-matrix-800 border border-matrix-600 rounded-xl p-5">
          <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Twilio Balance</p>
          <p class="text-2xl font-bold font-mono" :class="twilioLow ? 'text-red-400' : 'text-neon'">
            {{ data.twilio?.balance ? `$${parseFloat(data.twilio.balance).toFixed(2)}` : 'N/A' }}
          </p>
          <p class="text-xs text-gray-500 mt-1">{{ data.twilio?.currency || 'USD' }}</p>
          <p v-if="twilioLow" class="text-xs text-red-400 mt-2">Low balance - add funds</p>
        </div>

        <div class="bg-matrix-800 border border-matrix-600 rounded-xl p-5">
          <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Est. Cost (Month)</p>
          <p class="text-2xl font-bold font-mono text-yellow-400">${{ data.costs?.estimated_month_usd || '0' }}</p>
          <p class="text-xs text-gray-500 mt-1">{{ data.minutes?.this_month || 0 }} minutes used</p>
        </div>

        <div class="bg-matrix-800 border border-matrix-600 rounded-xl p-5">
          <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Revenue</p>
          <p class="text-2xl font-bold font-mono text-neon">${{ data.revenue_usd || '0' }}</p>
          <p class="text-xs text-gray-500 mt-1">From paid calls</p>
        </div>
      </div>

      <!-- Call Breakdown -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-matrix-800 border border-matrix-600 rounded-xl p-4">
          <p class="text-xs text-gray-500 uppercase">Total Calls</p>
          <p class="text-xl font-bold font-mono mt-1">{{ data.calls?.total }}</p>
        </div>
        <div class="bg-matrix-800 border border-matrix-600 rounded-xl p-4">
          <p class="text-xs text-gray-500 uppercase">Admin (Free)</p>
          <p class="text-xl font-bold font-mono mt-1 text-purple-400">{{ data.calls?.admin }}</p>
        </div>
        <div class="bg-matrix-800 border border-matrix-600 rounded-xl p-4">
          <p class="text-xs text-gray-500 uppercase">Trial (Free)</p>
          <p class="text-xl font-bold font-mono mt-1 text-blue-400">{{ data.calls?.trial }}</p>
        </div>
        <div class="bg-matrix-800 border border-matrix-600 rounded-xl p-4">
          <p class="text-xs text-gray-500 uppercase">Paid</p>
          <p class="text-xl font-bold font-mono mt-1 text-neon">{{ data.calls?.paid }}</p>
        </div>
      </div>

      <!-- This Month vs Today -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="bg-matrix-800 border border-matrix-600 rounded-xl p-5">
          <h3 class="text-sm font-semibold text-gray-400 uppercase mb-3">Today</h3>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <p class="text-xs text-gray-500">Calls</p>
              <p class="font-bold font-mono text-lg">{{ data.calls?.today }}</p>
            </div>
            <div>
              <p class="text-xs text-gray-500">Completed</p>
              <p class="font-bold font-mono text-lg text-neon">{{ data.calls?.completed_today }}</p>
            </div>
          </div>
        </div>

        <div class="bg-matrix-800 border border-matrix-600 rounded-xl p-5">
          <h3 class="text-sm font-semibold text-gray-400 uppercase mb-3">This Month</h3>
          <div class="grid grid-cols-3 gap-3">
            <div>
              <p class="text-xs text-gray-500">Calls</p>
              <p class="font-bold font-mono text-lg">{{ data.calls?.this_month }}</p>
            </div>
            <div>
              <p class="text-xs text-gray-500">Completed</p>
              <p class="font-bold font-mono text-lg text-neon">{{ data.calls?.completed_month }}</p>
            </div>
            <div>
              <p class="text-xs text-gray-500">Minutes</p>
              <p class="font-bold font-mono text-lg">{{ data.minutes?.this_month }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Cost Breakdown -->
      <div class="bg-matrix-800 border border-matrix-600 rounded-xl p-5">
        <h3 class="text-sm font-semibold text-gray-400 uppercase mb-3">Cost Summary</h3>
        <table class="w-full text-sm">
          <tbody>
            <tr class="border-b border-matrix-700">
              <td class="py-2 text-gray-400">Cost per minute (Twilio + OpenAI)</td>
              <td class="py-2 text-right font-mono">${{ data.costs?.cost_per_minute_usd }}/min</td>
            </tr>
            <tr class="border-b border-matrix-700">
              <td class="py-2 text-gray-400">Total minutes used (all time)</td>
              <td class="py-2 text-right font-mono">{{ data.minutes?.total }} min</td>
            </tr>
            <tr class="border-b border-matrix-700">
              <td class="py-2 text-gray-400">Estimated total cost (all time)</td>
              <td class="py-2 text-right font-mono text-yellow-400">${{ data.costs?.estimated_total_usd }}</td>
            </tr>
            <tr>
              <td class="py-2 text-gray-400">Estimated this month</td>
              <td class="py-2 text-right font-mono text-yellow-400">${{ data.costs?.estimated_month_usd }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'

const data = ref({})
const loading = ref(true)

const twilioLow = computed(() => {
  const b = parseFloat(data.value.twilio?.balance || 0)
  return b < 10
})

onMounted(async () => {
  try {
    const { data: d } = await axios.get('/admin-api/billing')
    data.value = d
  } catch {} finally {
    loading.value = false
  }
})
</script>
