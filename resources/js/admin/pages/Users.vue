<template>
  <div class="p-6 space-y-4">
    <h1 class="text-2xl font-bold font-mono">Users</h1>

    <input v-model="search" @input="debouncedFetch" placeholder="Search by name or email..."
      class="bg-matrix-800 border border-matrix-600 rounded-lg px-3 py-2 text-sm text-white
             placeholder-gray-500 focus:outline-none focus:border-neon/50 w-72" />

    <div class="bg-matrix-800 border border-matrix-600 rounded-xl overflow-hidden">
      <table class="w-full text-sm">
        <thead>
          <tr class="text-gray-500 text-xs uppercase border-b border-matrix-600">
            <th class="text-left p-3">Name</th>
            <th class="text-left p-3">Email</th>
            <th class="text-left p-3">Calls</th>
            <th class="text-left p-3">Plan</th>
            <th class="text-left p-3">Admin</th>
            <th class="text-left p-3">Joined</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="user in users" :key="user.id" @click="$router.push('/admin/users/' + user.id)" class="border-b border-matrix-700 hover:bg-matrix-700 cursor-pointer transition">
            <td class="p-3 font-medium">{{ user.name }}</td>
            <td class="p-3 text-gray-400">{{ user.email }}</td>
            <td class="p-3 font-mono">{{ user.joke_calls_count }}</td>
            <td class="p-3">
              <span v-if="user.subscription_plan" class="px-2 py-0.5 rounded-full text-xs bg-neon/20 text-neon">
                {{ user.subscription_plan }}
              </span>
              <span v-else class="text-gray-600 text-xs">-</span>
            </td>
            <td class="p-3">
              <span v-if="user.is_admin" class="text-neon text-xs">&#10003;</span>
            </td>
            <td class="p-3 text-gray-400 text-xs">{{ formatDate(user.created_at) }}</td>
          </tr>
          <tr v-if="!users.length">
            <td colspan="6" class="p-8 text-center text-gray-500">No users found</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

const users = ref([])
const search = ref('')
let timer

function debouncedFetch() {
  clearTimeout(timer)
  timer = setTimeout(fetchUsers, 300)
}

async function fetchUsers() {
  try {
    const { data } = await axios.get('/admin-api/users', { params: { search: search.value } })
    users.value = data.data
  } catch {}
}

function formatDate(d) { return d ? new Date(d).toLocaleDateString() : '' }

onMounted(fetchUsers)
</script>
