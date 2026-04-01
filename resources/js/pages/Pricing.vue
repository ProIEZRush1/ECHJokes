<template>
  <div class="min-h-screen flex flex-col items-center px-4 py-16">
    <router-link to="/" class="text-3xl font-bold font-mono text-neon mb-2">ECHJokes</router-link>
    <p class="text-gray-400 mb-12 text-center max-w-md">
      Bromas telefonicas con IA. Elige tu plan y empieza a bromear.
    </p>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 max-w-4xl w-full">
      <div v-for="plan in plans" :key="plan.id"
        class="bg-matrix-800 border rounded-2xl p-6 relative flex flex-col"
        :class="plan.is_popular ? 'border-neon scale-105' : 'border-matrix-600'">

        <div v-if="plan.is_popular" class="absolute -top-3 left-1/2 -translate-x-1/2">
          <span class="px-3 py-0.5 bg-neon text-matrix-900 text-xs font-bold rounded-full">MAS POPULAR</span>
        </div>

        <h3 class="text-xl font-bold">{{ plan.name }}</h3>
        <p class="text-sm text-gray-400 mt-1">{{ plan.description }}</p>

        <div class="mt-4">
          <span class="text-4xl font-bold font-mono text-neon">${{ plan.price_mxn }}</span>
          <span class="text-gray-500 text-sm"> MXN</span>
        </div>

        <p class="text-xs text-gray-500 mt-1">{{ plan.calls_included }} llamada{{ plan.calls_included > 1 ? 's' : '' }} &middot; hasta {{ plan.max_duration_minutes }} min</p>

        <ul class="mt-4 space-y-2 flex-1">
          <li v-for="f in (plan.features || [])" :key="f" class="flex items-start gap-2 text-sm text-gray-300">
            <span class="text-neon mt-0.5">&#10003;</span>
            <span>{{ f }}</span>
          </li>
        </ul>

        <button @click="buy(plan)" :disabled="buying === plan.id"
          class="mt-6 w-full py-3 rounded-xl font-bold text-sm transition"
          :class="plan.is_popular
            ? 'bg-neon text-matrix-900 hover:shadow-neon'
            : 'bg-matrix-700 text-white hover:bg-matrix-600 border border-matrix-600'">
          {{ buying === plan.id ? 'Redirigiendo...' : 'Comprar' }}
        </button>
      </div>
    </div>

    <div class="mt-8 text-center text-sm text-gray-500">
      <router-link to="/" class="hover:text-neon">Prueba gratis</router-link>
      <span class="mx-2">&middot;</span>
      <router-link to="/login" class="hover:text-neon">Iniciar sesion</router-link>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'

const router = useRouter()
const plans = ref([])
const buying = ref(null)

onMounted(async () => {
  try {
    const { data } = await axios.get('/user-api/plans')
    plans.value = data
  } catch {}
})

async function buy(plan) {
  // Check if logged in first
  try {
    await axios.get('/user-api/me')
  } catch {
    router.push('/login')
    return
  }

  buying.value = plan.id
  try {
    const { data } = await axios.post('/user-api/buy-plan', { plan_id: plan.id })
    window.location.href = data.checkout_url
  } catch (e) {
    alert(e.response?.data?.error || 'Error')
    buying.value = null
  }
}
</script>
