<template>
  <div class="p-6 space-y-6">
    <h1 class="text-2xl font-bold font-mono">Dashboard</h1>

    <!-- Stats Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
      <div v-for="stat in statCards" :key="stat.label"
        class="bg-matrix-800 border border-matrix-600 rounded-xl p-4">
        <p class="text-xs text-gray-500 uppercase tracking-wider">{{ stat.label }}</p>
        <p class="text-2xl font-bold font-mono mt-1" :class="stat.color">{{ stat.value }}</p>
      </div>
    </div>

    <!-- Recent Calls -->
    <div class="bg-matrix-800 border border-matrix-600 rounded-xl">
      <div class="p-4 border-b border-matrix-600 flex items-center justify-between">
        <h2 class="font-semibold">Recent Calls</h2>
        <router-link to="/panel/calls" class="text-neon text-sm hover:underline">View all</router-link>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="text-gray-500 text-xs uppercase border-b border-matrix-600">
              <th class="text-left p-3">Phone</th>
              <th class="text-left p-3">Scenario</th>
              <th class="text-left p-3">Status</th>
              <th class="text-left p-3">Duration</th>
              <th class="text-left p-3">Time</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="call in recentCalls" :key="call.id"
              @click="$router.push('/panel/calls/' + call.id)"
              class="border-b border-matrix-700 hover:bg-matrix-700 cursor-pointer transition">
              <td class="p-3 font-mono text-xs">{{ maskPhone(call.phone_number) }}</td>
              <td class="p-3 max-w-xs truncate">{{ call.custom_joke_prompt || '-' }}</td>
              <td class="p-3">
                <span class="px-2 py-0.5 rounded-full text-xs font-medium" :class="statusClass(call.status)">
                  {{ call.status }}
                </span>
              </td>
              <td class="p-3 font-mono text-xs">{{ formatDuration(call.call_duration_seconds) }}</td>
              <td class="p-3 text-gray-400 text-xs">{{ timeAgo(call.created_at) }}</td>
            </tr>
            <tr v-if="!recentCalls.length">
              <td colspan="5" class="p-6 text-center text-gray-500">No calls yet</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import axios from 'axios'

const stats = ref({})
const recentCalls = ref([])
let interval

const statCards = computed(() => [
  { label: 'Calls Today', value: stats.value.calls_today ?? '-', color: 'text-white' },
  { label: 'Completed', value: stats.value.completed_today ?? '-', color: 'text-neon' },
  { label: 'Active Now', value: stats.value.active_now ?? '-', color: stats.value.active_now > 0 ? 'text-red-400' : 'text-gray-500' },
  { label: 'Total Calls', value: stats.value.total_calls ?? '-', color: 'text-gray-300' },
])

function statusClass(status) {
  const map = {
    completed: 'bg-green-500/20 text-green-400',
    failed: 'bg-red-500/20 text-red-400',
    voicemail: 'bg-yellow-500/20 text-yellow-400',
    calling: 'bg-blue-500/20 text-blue-400',
    in_progress: 'bg-purple-500/20 text-purple-400',
  }
  return map[status] || 'bg-gray-500/20 text-gray-400'
}

function maskPhone(phone) {
  if (!phone || phone.length < 6) return phone
  return phone.slice(0, 6) + '****' + phone.slice(-2)
}

function formatDuration(seconds) {
  if (!seconds) return '-'
  const m = Math.floor(seconds / 60)
  const s = seconds % 60
  return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`
}

function timeAgo(date) {
  if (!date) return ''
  const d = new Date(date)
  const now = new Date()
  const diff = Math.floor((now - d) / 1000)
  if (diff < 60) return 'just now'
  if (diff < 3600) return `${Math.floor(diff / 60)}m ago`
  if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`
  return d.toLocaleDateString()
}

async function fetchData() {
  try {
    const [s, c] = await Promise.all([
      axios.get('/admin-api/stats'),
      axios.get('/admin-api/calls', { params: { per_page: 8 } }),
    ])
    stats.value = s.data
    recentCalls.value = c.data.data
  } catch {}
}

onMounted(() => {
  fetchData()
  interval = setInterval(fetchData, 10000)
})
onUnmounted(() => clearInterval(interval))
</script>
