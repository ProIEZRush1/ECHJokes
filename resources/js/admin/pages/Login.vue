<template>
  <div class="min-h-screen bg-app-glass flex items-center justify-center p-4 relative">
    <!-- Glow blobs -->
    <div class="pointer-events-none absolute -top-32 -right-32 w-[480px] h-[480px] rounded-full" style="background:radial-gradient(circle,rgba(57,255,20,0.18),transparent 60%);filter:blur(40px)" />
    <div class="pointer-events-none absolute -bottom-32 -left-32 w-[420px] h-[420px] rounded-full" style="background:radial-gradient(circle,rgba(120,80,255,0.15),transparent 60%);filter:blur(40px)" />

    <div class="w-full max-w-sm relative">
      <!-- Brand -->
      <div class="text-center mb-7 animate-[fade-in-up_0.5s_ease-out_both]">
        <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-gradient-to-br from-neon to-emerald-600 text-matrix-900 font-extrabold text-xl shadow-[0_0_36px_rgba(57,255,20,0.55)] mb-3">V</div>
        <h1 class="text-2xl font-bold tracking-tight">Vacilada</h1>
        <p class="text-gray-500 text-sm mt-1">Admin Console</p>
      </div>

      <!-- Form -->
      <form @submit.prevent="login" class="surface-card surface-card-edge p-6 rounded-2xl space-y-4 animate-[fade-in-up_0.55s_ease-out_both]" style="animation-delay:80ms">
        <UiInput v-model="email" label="Email" type="email" placeholder="admin@vacilada.com" required>
          <template #prefix>
            <Mail class="w-4 h-4" />
          </template>
        </UiInput>

        <UiInput v-model="password" label="Password" :type="showPassword ? 'text' : 'password'" placeholder="••••••••" required>
          <template #prefix>
            <Lock class="w-4 h-4" />
          </template>
          <template #suffix>
            <button type="button" class="text-gray-500 hover:text-white transition pointer-events-auto" @click="showPassword = !showPassword">
              <Eye v-if="!showPassword" class="w-4 h-4" />
              <EyeOff v-else class="w-4 h-4" />
            </button>
          </template>
        </UiInput>

        <UiButton type="submit" variant="primary" size="lg" :loading="loading" class="w-full">
          {{ loading ? 'Entrando…' : 'Sign in' }}
        </UiButton>
      </form>

      <p class="text-center text-xs text-gray-600 mt-5">vacilada.com · admin v1.0</p>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import { Mail, Lock, Eye, EyeOff } from 'lucide-vue-next'
import UiInput from '../components/UiInput.vue'
import UiButton from '../components/UiButton.vue'
import { useToast } from '../composables/useToast.js'

const router = useRouter()
const toast = useToast()
const email = ref('')
const password = ref('')
const showPassword = ref(false)
const loading = ref(false)

async function login() {
  loading.value = true
  try {
    await axios.get('/sanctum/csrf-cookie')
    await axios.post('/admin-api/login', { email: email.value, password: password.value })
    toast.success('Bienvenido de vuelta.')
    router.push('/admin')
  } catch (e) {
    toast.error(e.response?.data?.error || 'Login failed')
  } finally {
    loading.value = false
  }
}
</script>
