<template>
  <div class="min-h-screen bg-matrix-900 text-white">
    <!-- Top Nav -->
    <nav class="border-b border-matrix-600 bg-matrix-800">
      <div class="max-w-5xl mx-auto px-4 py-3 flex items-center justify-between">
        <router-link to="/" class="text-lg font-bold font-mono text-neon">ECHJokes</router-link>

        <div class="flex items-center gap-4">
          <router-link v-for="item in nav" :key="item.to" :to="item.to"
            class="text-sm transition"
            :class="$route.path === item.to ? 'text-neon' : 'text-gray-400 hover:text-white'">
            {{ item.label }}
          </router-link>

          <div v-if="user" class="flex items-center gap-2 ml-4 pl-4 border-l border-matrix-600">
            <div class="w-7 h-7 rounded-full bg-neon/20 flex items-center justify-center text-neon text-xs font-bold">
              {{ user.name?.[0] }}
            </div>
            <span class="text-xs text-gray-400">{{ user.credits }} cr</span>
            <button @click="logout" class="text-xs text-gray-500 hover:text-red-400 ml-1">Salir</button>
          </div>
        </div>
      </div>
    </nav>

    <main class="max-w-5xl mx-auto px-4 py-8">
      <router-view />
    </main>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'

const router = useRouter()
const user = ref(null)

const nav = [
  { to: '/dashboard', label: 'Mis Llamadas' },
  { to: '/dashboard/new', label: 'Nueva Broma' },
  { to: '/pricing', label: 'Planes' },
]

onMounted(async () => {
  try {
    const { data } = await axios.get('/user-api/me')
    user.value = data.user
  } catch {
    router.push('/login')
  }
})

async function logout() {
  await axios.post('/user-api/logout')
  router.push('/')
}
</script>
