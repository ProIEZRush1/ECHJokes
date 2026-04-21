<template>
  <div class="p-4 md:p-6 space-y-6" v-if="data">
    <div class="flex items-center gap-4">
      <router-link to="/admin/users" class="text-gray-400 hover:text-white">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
      </router-link>
      <h1 class="text-2xl font-bold font-mono">{{ data.user.name }}</h1>
      <span v-if="data.user.is_admin" class="px-2 py-0.5 rounded-full text-xs bg-neon/20 text-neon">Admin</span>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- User Info -->
      <div class="space-y-4">
        <div class="bg-matrix-800 border border-matrix-600 rounded-xl p-5">
          <h2 class="text-sm font-semibold text-gray-400 uppercase mb-3">Info</h2>
          <div class="space-y-2 text-sm">
            <div><span class="text-gray-500">Email:</span> <span class="text-white">{{ data.user.email }}</span></div>
            <div><span class="text-gray-500">Phone:</span> <span class="text-white">{{ data.user.phone || '-' }}</span></div>
            <div><span class="text-gray-500">Joined:</span> <span class="text-white">{{ new Date(data.user.created_at).toLocaleDateString() }}</span></div>
            <div><span class="text-gray-500">Plan:</span> <span class="text-neon font-mono">{{ data.user.subscription_plan || 'None' }}</span></div>
            <div><span class="text-gray-500">Credits:</span> <span class="text-neon font-mono text-lg">{{ data.credits }}</span></div>
            <div>
              <span class="text-gray-500">Chistes:</span>
              <span class="text-neon font-mono text-lg ml-1">{{ data.jokes }}</span>
              <span v-if="data.jokes_reset_at" class="text-[10px] text-gray-500 ml-2">
                reset {{ new Date(data.jokes_reset_at).toLocaleDateString() }}
              </span>
            </div>
          </div>
        </div>

        <!-- Call Stats -->
        <div class="bg-matrix-800 border border-matrix-600 rounded-xl p-5">
          <h2 class="text-sm font-semibold text-gray-400 uppercase mb-3">Call Stats</h2>
          <div class="grid grid-cols-3 gap-2 text-center">
            <div class="bg-matrix-700 rounded-lg p-2">
              <p class="text-lg font-bold font-mono">{{ data.call_stats.total }}</p>
              <p class="text-[10px] text-gray-500">Total</p>
            </div>
            <div class="bg-matrix-700 rounded-lg p-2">
              <p class="text-lg font-bold font-mono text-neon">{{ data.call_stats.completed }}</p>
              <p class="text-[10px] text-gray-500">Completed</p>
            </div>
            <div class="bg-matrix-700 rounded-lg p-2">
              <p class="text-lg font-bold font-mono text-blue-400">{{ data.call_stats.paid }}</p>
              <p class="text-[10px] text-gray-500">Paid</p>
            </div>
          </div>
        </div>

        <!-- Stripe Info -->
        <div v-if="data.stripe" class="bg-matrix-800 border border-matrix-600 rounded-xl p-5">
          <h2 class="text-sm font-semibold text-gray-400 uppercase mb-3">Stripe</h2>
          <div v-if="data.stripe.error" class="text-xs text-gray-500">{{ data.stripe.error }}</div>
          <template v-else>
            <p class="text-xs text-gray-500 mb-2">ID: {{ data.stripe.customer_id }}</p>
            <div v-for="card in data.stripe.cards" :key="card.last4"
              class="flex items-center gap-2 bg-matrix-700 rounded-lg p-2 mb-1">
              <span class="text-sm font-bold uppercase">{{ card.brand }}</span>
              <span class="font-mono text-sm">****{{ card.last4 }}</span>
              <span class="text-xs text-gray-500 ml-auto">{{ card.exp_month }}/{{ card.exp_year }}</span>
            </div>
            <p v-if="!data.stripe.cards?.length" class="text-xs text-gray-500">No cards on file</p>
          </template>
        </div>

        <!-- Admin Actions -->
        <div class="bg-matrix-800 border border-neon/20 rounded-xl p-5">
          <h2 class="text-sm font-semibold text-gray-400 uppercase mb-3">Admin Actions</h2>
          <div class="space-y-3">
            <div>
              <label class="block text-xs text-gray-500 mb-1">Set Credits</label>
              <div class="flex gap-2">
                <input v-model.number="editCredits" type="number" min="0"
                  class="flex-1 bg-matrix-700 border border-matrix-600 rounded-lg px-3 py-2 text-sm text-white" />
                <button @click="setCredits" class="px-3 py-2 rounded-lg bg-neon text-matrix-900 text-xs font-bold">Set</button>
              </div>
            </div>
            <div>
              <label class="block text-xs text-gray-500 mb-1">Set Chistes</label>
              <div class="flex gap-2">
                <input v-model.number="editJokes" type="number" min="0"
                  class="flex-1 bg-matrix-700 border border-matrix-600 rounded-lg px-3 py-2 text-sm text-white" />
                <button @click="setJokes" class="px-3 py-2 rounded-lg bg-neon text-matrix-900 text-xs font-bold">Set</button>
              </div>
            </div>
            <div>
              <label class="block text-xs text-gray-500 mb-1">Assign Plan (no charge)</label>
              <div class="flex gap-2">
                <select v-model="editPlan"
                  class="flex-1 bg-matrix-700 border border-matrix-600 rounded-lg px-3 py-2 text-sm text-white">
                  <option value="">None</option>
                  <option v-for="p in plans" :key="p.slug" :value="p.slug">{{ p.name }} ({{ p.calls_included }} calls)</option>
                </select>
                <button @click="setPlan" class="px-3 py-2 rounded-lg bg-neon text-matrix-900 text-xs font-bold">Set</button>
              </div>
            </div>
            <div>
              <label class="flex items-center gap-2 text-sm text-gray-300 cursor-pointer">
                <input type="checkbox" v-model="editAdmin" @change="setAdmin" class="accent-[#39FF14]" />
                Admin access
              </label>
            </div>
          </div>
        </div>

        <!-- Danger zone -->
        <div v-if="!data.user.is_admin" class="bg-matrix-800 border border-red-500/30 rounded-xl p-5">
          <h2 class="text-sm font-semibold text-red-400 uppercase mb-2">Danger zone</h2>
          <p class="text-xs text-gray-500 mb-3">Elimina al usuario, sus créditos, llamadas y referidos. Esta acción no se puede deshacer.</p>
          <button @click="deleteUser" :disabled="deleting"
            class="w-full py-2 rounded-lg bg-red-500/10 border border-red-500/40 text-red-400 text-xs font-bold hover:bg-red-500/20 transition disabled:opacity-50">
            {{ deleting ? 'Eliminando...' : 'Eliminar usuario completamente' }}
          </button>
        </div>
      </div>

      <!-- Recent Calls -->
      <div class="lg:col-span-2">
        <div class="bg-matrix-800 border border-matrix-600 rounded-xl">
          <div class="p-4 border-b border-matrix-600">
            <h2 class="font-semibold">Recent Calls</h2>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="text-gray-500 text-xs uppercase border-b border-matrix-600">
                  <th class="text-left p-3">Phone</th>
                  <th class="text-left p-3">Scenario</th>
                  <th class="text-left p-3">Status</th>
                  <th class="text-left p-3">Source</th>
                  <th class="text-left p-3">Duration</th>
                  <th class="text-left p-3">Date</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="call in data.calls" :key="call.id"
                  @click="$router.push('/admin/calls/' + call.id)"
                  class="border-b border-matrix-700 hover:bg-matrix-700 cursor-pointer transition">
                  <td class="p-3 font-mono text-xs">{{ call.phone_number }}</td>
                  <td class="p-3 max-w-xs truncate text-gray-300">{{ call.custom_joke_prompt || '-' }}</td>
                  <td class="p-3">
                    <span class="px-2 py-0.5 rounded-full text-xs font-medium" :class="statusClass(call.status)">{{ call.status }}</span>
                  </td>
                  <td class="p-3">
                    <span class="px-1.5 py-0.5 rounded text-[10px]" :class="sourceClass(call.joke_source)">{{ sourceLabel(call.joke_source) }}</span>
                  </td>
                  <td class="p-3 font-mono text-xs">{{ call.call_duration_seconds ? `${Math.floor(call.call_duration_seconds/60)}:${String(call.call_duration_seconds%60).padStart(2,'0')}` : '-' }}</td>
                  <td class="p-3 text-gray-400 text-xs">{{ new Date(call.created_at).toLocaleString() }}</td>
                </tr>
                <tr v-if="!data.calls.length">
                  <td colspan="6" class="p-6 text-center text-gray-500">No calls</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div v-else class="p-6 text-center text-gray-500">Loading...</div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import axios from 'axios'

const route = useRoute()
const data = ref(null)
const plans = ref([])
const editCredits = ref(0)
const editJokes = ref(0)
const editPlan = ref('')
const editAdmin = ref(false)
const deleting = ref(false)

onMounted(async () => {
  const [u, p] = await Promise.all([
    axios.get(`/admin-api/users/${route.params.id}`),
    axios.get('/admin-api/plans'),
  ])
  data.value = u.data
  plans.value = p.data
  editCredits.value = u.data.credits
  editJokes.value = u.data.jokes ?? 0
  editPlan.value = u.data.user.subscription_plan || ''
  editAdmin.value = u.data.user.is_admin
})

async function setCredits() {
  await axios.put(`/admin-api/users/${route.params.id}`, { credits: editCredits.value })
  data.value.credits = editCredits.value
}

async function setJokes() {
  await axios.put(`/admin-api/users/${route.params.id}`, { jokes: editJokes.value })
  data.value.jokes = editJokes.value
}

async function setPlan() {
  await axios.put(`/admin-api/users/${route.params.id}`, { subscription_plan: editPlan.value || null })
  data.value.user.subscription_plan = editPlan.value
}

async function setAdmin() {
  await axios.put(`/admin-api/users/${route.params.id}`, { is_admin: editAdmin.value })
}

async function deleteUser() {
  const name = data.value?.user?.name || data.value?.user?.email || 'este usuario'
  if (!confirm(`¿Eliminar COMPLETAMENTE a ${name}? Se borrarán sus créditos, llamadas y referidos. No se puede deshacer.`)) return
  deleting.value = true
  try {
    await axios.delete(`/admin-api/users/${route.params.id}`)
    window.location.href = '/admin/users'
  } catch (e) {
    alert(e.response?.data?.error || 'No se pudo eliminar.')
  } finally {
    deleting.value = false
  }
}

function statusClass(s) {
  return { completed: 'bg-green-500/20 text-green-400', failed: 'bg-red-500/20 text-red-400', voicemail: 'bg-yellow-500/20 text-yellow-400' }[s] || 'bg-blue-500/20 text-blue-400'
}
function sourceClass(s) {
  return { trial: 'bg-blue-500/20 text-blue-400', paid: 'bg-neon/20 text-neon', custom: 'bg-purple-500/20 text-purple-400' }[s] || 'bg-gray-500/20 text-gray-400'
}
function sourceLabel(s) {
  return { trial: 'Trial', paid: 'Paid', custom: 'Admin', prank: 'Admin' }[s] || s || '-'
}
</script>
