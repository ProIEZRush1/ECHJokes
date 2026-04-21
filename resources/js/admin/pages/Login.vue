<template>
  <div class="min-h-screen bg-matrix-900 flex items-center justify-center p-4">
    <div class="w-full max-w-sm">
      <div class="text-center mb-8">
        <h1 class="text-3xl font-bold font-mono text-neon">Vacilada</h1>
        <p class="text-gray-500 text-sm mt-1">Admin Panel</p>
      </div>

      <form @submit.prevent="login" class="bg-matrix-800 border border-matrix-600 rounded-2xl p-6 space-y-4">
        <div>
          <label class="block text-xs text-gray-400 mb-1.5 uppercase tracking-wider">Email</label>
          <input v-model="email" type="email" required autofocus
            class="w-full bg-matrix-700 border border-matrix-600 rounded-lg px-3 py-2.5 text-sm text-white
                   placeholder-gray-500 focus:outline-none focus:border-neon/50 focus:ring-1 focus:ring-neon/30 transition"
            placeholder="admin@vacilada.com" />
        </div>

        <div>
          <label class="block text-xs text-gray-400 mb-1.5 uppercase tracking-wider">Password</label>
          <input v-model="password" type="password" required
            class="w-full bg-matrix-700 border border-matrix-600 rounded-lg px-3 py-2.5 text-sm text-white
                   placeholder-gray-500 focus:outline-none focus:border-neon/50 focus:ring-1 focus:ring-neon/30 transition"
            placeholder="••••••••" />
        </div>

        <p v-if="error" class="text-red-400 text-xs">{{ error }}</p>

        <button type="submit" :disabled="loading"
          class="w-full py-2.5 rounded-lg bg-neon text-matrix-900 font-bold text-sm
                 hover:shadow-neon transition disabled:opacity-50">
          {{ loading ? 'Signing in...' : 'Sign In' }}
        </button>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'

const router = useRouter()
const email = ref('')
const password = ref('')
const error = ref('')
const loading = ref(false)

async function login() {
  loading.value = true
  error.value = ''
  try {
    await axios.get('/sanctum/csrf-cookie')
    await axios.post('/admin-api/login', { email: email.value, password: password.value })
    router.push('/admin')
  } catch (e) {
    error.value = e.response?.data?.error || 'Login failed'
  } finally {
    loading.value = false
  }
}
</script>
