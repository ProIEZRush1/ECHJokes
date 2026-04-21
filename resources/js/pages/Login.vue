<template>
  <div class="min-h-screen flex items-center justify-center px-4">
    <div class="w-full max-w-sm">
      <div class="text-center mb-8">
        <router-link to="/" class="text-3xl font-bold font-mono text-neon">Vacilada</router-link>
        <p class="text-gray-500 text-sm mt-1">{{ isRegister ? 'Crea tu cuenta' : 'Inicia sesion' }}</p>
      </div>

      <div class="bg-matrix-800 border border-matrix-600 rounded-2xl p-6 space-y-4">
        <div v-if="isRegister">
          <label class="block text-xs text-gray-400 uppercase mb-1">Nombre</label>
          <input v-model="name" type="text"
            class="w-full bg-matrix-700 border border-matrix-600 rounded-lg px-3 py-2.5 text-sm text-white placeholder-gray-500 focus:outline-none focus:border-neon/50"
            placeholder="Tu nombre" />
        </div>

        <div>
          <label class="block text-xs text-gray-400 uppercase mb-1">Email</label>
          <input v-model="email" type="email"
            class="w-full bg-matrix-700 border border-matrix-600 rounded-lg px-3 py-2.5 text-sm text-white placeholder-gray-500 focus:outline-none focus:border-neon/50"
            placeholder="tu@email.com" />
        </div>

        <div>
          <label class="block text-xs text-gray-400 uppercase mb-1">Password</label>
          <input v-model="password" type="password"
            class="w-full bg-matrix-700 border border-matrix-600 rounded-lg px-3 py-2.5 text-sm text-white placeholder-gray-500 focus:outline-none focus:border-neon/50"
            placeholder="******" />
        </div>

        <p v-if="error" class="text-red-400 text-xs">{{ error }}</p>

        <button @click="submit" :disabled="loading"
          class="w-full py-2.5 rounded-lg bg-neon text-matrix-900 font-bold text-sm hover:shadow-neon transition disabled:opacity-50">
          {{ loading ? '...' : (isRegister ? 'Crear cuenta' : 'Entrar') }}
        </button>

        <p class="text-center text-xs text-gray-500">
          {{ isRegister ? 'Ya tienes cuenta?' : 'No tienes cuenta?' }}
          <button @click="isRegister = !isRegister" class="text-neon hover:underline ml-1">
            {{ isRegister ? 'Inicia sesion' : 'Registrate' }}
          </button>
        </p>
      </div>

      <div class="text-center mt-4">
        <router-link to="/" class="text-xs text-gray-500 hover:text-neon">Volver al inicio</router-link>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import axios from 'axios'

const router = useRouter()
const route = useRoute()
const isRegister = ref(!!route.query.ref)
const name = ref('')
const email = ref('')
const password = ref('')
const error = ref('')
const loading = ref(false)

async function submit() {
  error.value = ''
  loading.value = true
  try {
    const endpoint = isRegister.value ? '/user-api/register' : '/user-api/login'
    const refCode = route.query.ref || localStorage.getItem('vacilada_ref') || ''
    const payload = isRegister.value
      ? { name: name.value, email: email.value, password: password.value, ref: refCode }
      : { email: email.value, password: password.value }
    await axios.post(endpoint, payload)
    router.push('/dashboard')
  } catch (e) {
    error.value = e.response?.data?.error || e.response?.data?.message || 'Error'
  } finally { loading.value = false }
}
</script>
