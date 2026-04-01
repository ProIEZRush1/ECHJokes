<template>
  <div class="min-h-screen bg-matrix-900 text-white font-sans">
    <!-- Sidebar -->
    <aside class="fixed left-0 top-0 h-screen w-56 bg-matrix-800 border-r border-matrix-600 flex flex-col z-50">
      <div class="p-4 border-b border-matrix-600">
        <h1 class="text-lg font-bold font-mono text-neon">ECHJokes</h1>
        <p class="text-xs text-gray-500 mt-0.5">Admin Panel</p>
      </div>

      <nav class="flex-1 p-3 space-y-1">
        <router-link v-for="item in nav" :key="item.to" :to="item.to"
          class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition-all"
          :class="$route.path === item.to
            ? 'bg-neon/10 text-neon border border-neon/20'
            : 'text-gray-400 hover:text-white hover:bg-matrix-700'">
          <span class="text-base">{{ item.icon }}</span>
          <span>{{ item.label }}</span>
        </router-link>
      </nav>

      <div class="p-3 border-t border-matrix-600">
        <div class="flex items-center gap-2 px-3 py-2">
          <div class="w-7 h-7 rounded-full bg-neon/20 flex items-center justify-center text-neon text-xs font-bold">
            {{ user?.name?.[0] || '?' }}
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-xs font-medium truncate">{{ user?.name }}</p>
          </div>
          <button @click="logout" class="text-gray-500 hover:text-red-400 text-xs" title="Logout">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
            </svg>
          </button>
        </div>
      </div>
    </aside>

    <!-- Main -->
    <main class="ml-56 min-h-screen">
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
  { to: '/admin', icon: '📊', label: 'Dashboard' },
  { to: '/admin/calls', icon: '📞', label: 'Calls' },
  { to: '/admin/launch', icon: '🚀', label: 'Launch Call' },
  { to: '/admin/users', icon: '👥', label: 'Users' },
]

onMounted(async () => {
  try {
    const { data } = await axios.get('/admin-api/me')
    user.value = data.user
  } catch {
    router.push('/admin/login')
  }
})

async function logout() {
  await axios.post('/admin-api/logout')
  router.push('/admin/login')
}
</script>
