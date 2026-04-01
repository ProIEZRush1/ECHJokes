<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold font-mono">Mis Llamadas</h1>
      <router-link to="/dashboard/new"
        class="px-4 py-2 bg-neon text-matrix-900 rounded-lg font-bold text-sm hover:shadow-neon transition">
        + Nueva Broma
      </router-link>
    </div>

    <div v-if="calls.length === 0 && !loading" class="text-center py-16">
      <p class="text-5xl mb-4">&#x1F4DE;</p>
      <p class="text-gray-400 mb-4">Aun no has hecho ninguna broma</p>
      <router-link to="/dashboard/new"
        class="inline-block px-6 py-3 bg-neon text-matrix-900 rounded-xl font-bold hover:shadow-neon transition">
        Hacer mi primera broma
      </router-link>
    </div>

    <div v-else class="space-y-3">
      <div v-for="call in calls" :key="call.id"
        @click="$router.push('/call/' + call.id + '/status')"
        class="bg-matrix-800 border border-matrix-600 rounded-xl p-4 hover:border-neon/30 cursor-pointer transition flex items-center gap-4">
        <div class="flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center text-lg"
          :class="statusBg(call.status)">
          {{ statusEmoji(call.status) }}
        </div>
        <div class="flex-1 min-w-0">
          <p class="text-sm text-white truncate">{{ call.custom_joke_prompt || 'Sin escenario' }}</p>
          <p class="text-xs text-gray-500 mt-0.5">{{ call.phone_number }} &middot; {{ formatDate(call.created_at) }}</p>
        </div>
        <div class="text-right">
          <span class="px-2 py-0.5 rounded-full text-xs font-medium" :class="statusClass(call.status)">
            {{ statusLabel(call.status) }}
          </span>
          <p v-if="call.call_duration_seconds" class="text-xs text-gray-500 mt-1 font-mono">
            {{ formatDuration(call.call_duration_seconds) }}
          </p>
        </div>
        <div v-if="call.recording_url" class="text-neon text-xs">&#9654;</div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

const calls = ref([])
const loading = ref(true)

onMounted(async () => {
  try {
    const { data } = await axios.get('/user-api/calls')
    calls.value = data.data
  } catch {} finally { loading.value = false }
})

function statusEmoji(s) { return { completed: '&#x2705;', failed: '&#x274C;', voicemail: '&#x1F4E8;', calling: '&#x1F4DE;', in_progress: '&#x1F5E3;' }[s] || '&#x23F3;' }
function statusBg(s) { return { completed: 'bg-green-500/20', failed: 'bg-red-500/20', voicemail: 'bg-yellow-500/20' }[s] || 'bg-blue-500/20' }
function statusClass(s) { return { completed: 'bg-green-500/20 text-green-400', failed: 'bg-red-500/20 text-red-400', voicemail: 'bg-yellow-500/20 text-yellow-400' }[s] || 'bg-blue-500/20 text-blue-400' }
function statusLabel(s) { return { completed: 'Completada', failed: 'Fallida', voicemail: 'Buzon', calling: 'Llamando', in_progress: 'En curso' }[s] || s }
function formatDuration(s) { return `${String(Math.floor(s/60)).padStart(2,'0')}:${String(s%60).padStart(2,'0')}` }
function formatDate(d) { return new Date(d).toLocaleDateString('es-MX', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }) }
</script>
