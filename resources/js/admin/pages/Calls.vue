<template>
  <div class="p-6 space-y-4">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold font-mono">Calls</h1>
      <router-link to="/admin/launch"
        class="px-4 py-2 bg-neon text-matrix-900 rounded-lg font-bold text-sm hover:shadow-neon transition">
        + New Call
      </router-link>
    </div>

    <!-- Filters -->
    <div class="flex gap-3 flex-wrap">
      <input v-model="search" @input="debouncedFetch" placeholder="Search..."
        class="bg-matrix-800 border border-matrix-600 rounded-lg px-3 py-2 text-sm text-white
               placeholder-gray-500 focus:outline-none focus:border-neon/50 w-64" />

      <select v-model="statusFilter" @change="fetchCalls"
        class="bg-matrix-800 border border-matrix-600 rounded-lg px-3 py-2 text-sm text-white">
        <option value="">All statuses</option>
        <option v-for="s in statuses" :key="s" :value="s">{{ s }}</option>
      </select>
    </div>

    <!-- Table -->
    <div class="bg-matrix-800 border border-matrix-600 rounded-xl overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="text-gray-500 text-xs uppercase border-b border-matrix-600">
              <th class="text-left p-3">Phone</th>
              <th class="text-left p-3">Scenario</th>
              <th class="text-left p-3">Status</th>
              <th class="text-left p-3">Duration</th>
              <th class="text-left p-3">Rec</th>
              <th class="text-left p-3">Date</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="call in calls" :key="call.id"
              @click="$router.push('/admin/calls/' + call.id)"
              class="border-b border-matrix-700 hover:bg-matrix-700 cursor-pointer transition">
              <td class="p-3 font-mono text-xs">{{ maskPhone(call.phone_number) }}</td>
              <td class="p-3 max-w-sm truncate text-gray-300">{{ call.custom_joke_prompt || '-' }}</td>
              <td class="p-3">
                <span class="px-2 py-0.5 rounded-full text-xs font-medium" :class="statusClass(call.status)">
                  {{ call.status }}
                </span>
              </td>
              <td class="p-3 font-mono text-xs">{{ formatDuration(call.call_duration_seconds) }}</td>
              <td class="p-3">
                <span v-if="call.recording_url" class="text-neon text-xs">&#9679;</span>
                <span v-else class="text-gray-600 text-xs">&#9675;</span>
              </td>
              <td class="p-3 text-gray-400 text-xs whitespace-nowrap">{{ formatDate(call.created_at) }}</td>
            </tr>
            <tr v-if="!calls.length && !loading">
              <td colspan="6" class="p-8 text-center text-gray-500">No calls found</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="meta.last_page > 1" class="p-3 border-t border-matrix-600 flex items-center justify-between text-xs text-gray-400">
        <span>Page {{ meta.current_page }} of {{ meta.last_page }} ({{ meta.total }} total)</span>
        <div class="flex gap-2">
          <button @click="page--; fetchCalls()" :disabled="page <= 1"
            class="px-3 py-1 rounded bg-matrix-700 hover:bg-matrix-600 disabled:opacity-30 transition">
            Prev
          </button>
          <button @click="page++; fetchCalls()" :disabled="page >= meta.last_page"
            class="px-3 py-1 rounded bg-matrix-700 hover:bg-matrix-600 disabled:opacity-30 transition">
            Next
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

const calls = ref([])
const meta = ref({})
const search = ref('')
const statusFilter = ref('')
const page = ref(1)
const loading = ref(false)
const statuses = ['calling', 'in_progress', 'completed', 'failed', 'voicemail']

let debounceTimer
function debouncedFetch() {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => { page.value = 1; fetchCalls() }, 300)
}

async function fetchCalls() {
  loading.value = true
  try {
    const { data } = await axios.get('/admin-api/calls', {
      params: { page: page.value, search: search.value, status: statusFilter.value }
    })
    calls.value = data.data
    meta.value = { current_page: data.current_page, last_page: data.last_page, total: data.total }
  } catch {} finally { loading.value = false }
}

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

function maskPhone(p) { return p && p.length > 6 ? p.slice(0, 6) + '****' + p.slice(-2) : p }
function formatDuration(s) { return s ? `${String(Math.floor(s/60)).padStart(2,'0')}:${String(s%60).padStart(2,'0')}` : '-' }
function formatDate(d) { return d ? new Date(d).toLocaleString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }) : '' }

onMounted(fetchCalls)
</script>
