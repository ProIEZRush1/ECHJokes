<template>
  <div class="min-h-screen flex items-center justify-center px-4">
    <div class="w-full max-w-sm">
      <div class="text-center mb-8">
        <router-link to="/" class="text-3xl font-bold font-mono text-neon">Vacilada</router-link>
        <p class="text-gray-500 text-sm mt-1">
          <template v-if="step === 'otp'">Verifica tu correo</template>
          <template v-else-if="isRegister">Crea tu cuenta</template>
          <template v-else>Inicia sesión</template>
        </p>
      </div>

      <!-- OTP step -->
      <div v-if="step === 'otp'" class="bg-matrix-800 border border-matrix-600 rounded-2xl p-6 space-y-4">
        <p class="text-sm text-gray-300">Enviamos un código de 6 dígitos a <strong class="text-white">{{ email }}</strong>. Ingrésalo para activar tu cuenta.</p>

        <div>
          <label class="block text-xs text-gray-400 uppercase mb-1">Código</label>
          <input v-model="otp" type="text" inputmode="numeric" maxlength="6" autocomplete="one-time-code"
            class="w-full bg-matrix-700 border border-matrix-600 rounded-lg px-3 py-3 text-center text-2xl tracking-[0.4em] font-mono text-white focus:outline-none focus:border-neon/50"
            placeholder="000000" @keyup.enter="verifyOtp" />
        </div>

        <p v-if="error" class="text-red-400 text-xs">{{ error }}</p>
        <p v-if="info" class="text-green-400 text-xs">{{ info }}</p>

        <button @click="verifyOtp" :disabled="loading || otp.length !== 6"
          class="w-full py-2.5 rounded-lg bg-neon text-matrix-900 font-bold text-sm hover:shadow-neon transition disabled:opacity-50">
          {{ loading ? '...' : 'Verificar y entrar' }}
        </button>

        <div class="text-center text-xs text-gray-500 space-x-2">
          <button @click="resendOtp" :disabled="loading || resendCooldown > 0" class="text-neon hover:underline disabled:opacity-50">
            {{ resendCooldown > 0 ? `Reenviar (${resendCooldown}s)` : 'Reenviar código' }}
          </button>
          <span>·</span>
          <button @click="step = 'form'; error=''" class="text-gray-500 hover:text-neon">Cambiar correo</button>
        </div>
      </div>

      <!-- Register / Login form step -->
      <div v-else class="bg-matrix-800 border border-matrix-600 rounded-2xl p-6 space-y-4">
        <div v-if="isRegister">
          <label class="block text-xs text-gray-400 uppercase mb-1">Nombre</label>
          <input v-model="name" type="text"
            class="w-full bg-matrix-700 border border-matrix-600 rounded-lg px-3 py-2.5 text-sm text-white placeholder-gray-500 focus:outline-none focus:border-neon/50"
            placeholder="Tu nombre" />
        </div>

        <div>
          <label class="block text-xs text-gray-400 uppercase mb-1">Email</label>
          <input v-model="email" type="email" autocomplete="email"
            class="w-full bg-matrix-700 border border-matrix-600 rounded-lg px-3 py-2.5 text-sm text-white placeholder-gray-500 focus:outline-none focus:border-neon/50"
            placeholder="tu@email.com" />
        </div>

        <div>
          <label class="block text-xs text-gray-400 uppercase mb-1">Password</label>
          <input v-model="password" type="password" :autocomplete="isRegister ? 'new-password' : 'current-password'"
            class="w-full bg-matrix-700 border border-matrix-600 rounded-lg px-3 py-2.5 text-sm text-white placeholder-gray-500 focus:outline-none focus:border-neon/50"
            placeholder="******" />
        </div>

        <label v-if="isRegister" class="flex items-start gap-2 text-[11px] text-gray-400 leading-relaxed cursor-pointer">
          <input type="checkbox" v-model="acceptedTerms" class="mt-0.5 accent-[#39FF14] shrink-0" />
          <span>
            Acepto los
            <router-link to="/terms" target="_blank" class="text-neon hover:underline">términos</router-link>
            y la
            <router-link to="/privacy" target="_blank" class="text-neon hover:underline">política de privacidad</router-link>.
            Entiendo que las llamadas son bromas ficticias entre amigos y me hago responsable del consentimiento de la persona que reciba la broma.
          </span>
        </label>

        <p v-if="error" class="text-red-400 text-xs">{{ error }}</p>
        <p v-if="info" class="text-green-400 text-xs">{{ info }}</p>

        <button @click="submit" :disabled="loading || (isRegister && !acceptedTerms)"
          class="w-full py-2.5 rounded-lg bg-neon text-matrix-900 font-bold text-sm hover:shadow-neon transition disabled:opacity-50">
          {{ loading ? '...' : (isRegister ? 'Crear cuenta' : 'Entrar') }}
        </button>

        <p class="text-center text-xs text-gray-500">
          {{ isRegister ? '¿Ya tienes cuenta?' : '¿No tienes cuenta?' }}
          <button @click="isRegister = !isRegister; error=''; info=''" class="text-neon hover:underline ml-1">
            {{ isRegister ? 'Inicia sesión' : 'Regístrate' }}
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
import { ref, onBeforeUnmount } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import axios from 'axios'

const router = useRouter()
const route = useRoute()
const isRegister = ref(!!route.query.ref)
const step = ref('form') // 'form' | 'otp'
const name = ref('')
const email = ref('')
const password = ref('')
const otp = ref('')
const error = ref('')
const info = ref('')
const loading = ref(false)
const acceptedTerms = ref(false)
const resendCooldown = ref(0)
let cooldownTimer = null

function startCooldown(s = 60) {
  resendCooldown.value = s
  clearInterval(cooldownTimer)
  cooldownTimer = setInterval(() => {
    resendCooldown.value--
    if (resendCooldown.value <= 0) clearInterval(cooldownTimer)
  }, 1000)
}
onBeforeUnmount(() => clearInterval(cooldownTimer))

async function submit() {
  error.value = ''; info.value = ''
  loading.value = true
  try {
    if (isRegister.value) {
      const refCode = route.query.ref || localStorage.getItem('vacilada_ref') || ''
      const { data } = await axios.post('/user-api/register', {
        name: name.value, email: email.value, password: password.value,
        ref: refCode, accept_terms: acceptedTerms.value,
      })
      if (data.status === 'pending_verification') {
        info.value = data.message
        step.value = 'otp'
        startCooldown(60)
        if (window.fbq) fbq('track', 'Lead', { content_name: 'Register' })
        return
      }
      if (window.fbq) fbq('track', 'CompleteRegistration')
      router.push('/dashboard')
    } else {
      const { data } = await axios.post('/user-api/login', {
        email: email.value, password: password.value,
      })
      router.push('/dashboard')
    }
  } catch (e) {
    if (e.response?.status === 403 && e.response.data?.pending_verification) {
      email.value = e.response.data.email || email.value
      info.value = 'Tu correo no está verificado. Te enviaremos un código nuevo.'
      step.value = 'otp'
      try {
        await axios.post('/user-api/resend-otp', { email: email.value })
        startCooldown(60)
      } catch {}
      return
    }
    error.value = e.response?.data?.error || e.response?.data?.message || 'Error'
  } finally { loading.value = false }
}

async function verifyOtp() {
  error.value = ''; info.value = ''
  loading.value = true
  try {
    await axios.post('/user-api/verify-otp', { email: email.value, code: otp.value })
    if (window.fbq) fbq('track', 'CompleteRegistration')
    router.push('/dashboard')
  } catch (e) {
    error.value = e.response?.data?.error || 'Código inválido.'
  } finally { loading.value = false }
}

async function resendOtp() {
  if (resendCooldown.value > 0) return
  error.value = ''; info.value = ''
  try {
    const { data } = await axios.post('/user-api/resend-otp', { email: email.value })
    info.value = data.message || 'Código reenviado.'
    startCooldown(60)
  } catch (e) {
    error.value = e.response?.data?.error || 'No se pudo reenviar.'
  }
}
</script>
