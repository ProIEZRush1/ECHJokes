<template>
  <div class="min-h-screen flex flex-col items-center px-4 py-12 md:py-16">
    <router-link to="/" class="text-3xl font-bold font-mono text-neon mb-2">ECHJokes</router-link>
    <p class="text-gray-400 mb-8 md:mb-12 text-center max-w-md">
      Bromas telefonicas con IA. Elige tu plan y empieza a bromear.
    </p>

    <!-- Plans -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 md:gap-6 max-w-4xl w-full">
      <div v-for="plan in plans" :key="plan.id"
        class="bg-matrix-800 border rounded-2xl p-5 md:p-6 relative flex flex-col"
        :class="plan.is_popular ? 'border-neon md:scale-105' : (isCurrentPlan(plan) ? 'border-neon/50' : 'border-matrix-600')">

        <div v-if="plan.is_popular && !isCurrentPlan(plan)" class="absolute -top-3 left-1/2 -translate-x-1/2">
          <span class="px-3 py-0.5 bg-neon text-matrix-900 text-xs font-bold rounded-full whitespace-nowrap">MAS POPULAR</span>
        </div>

        <div v-if="isCurrentPlan(plan)" class="absolute -top-3 left-1/2 -translate-x-1/2">
          <span class="px-3 py-0.5 bg-blue-500 text-white text-xs font-bold rounded-full whitespace-nowrap">TU PLAN</span>
        </div>

        <h3 class="text-xl font-bold">{{ plan.name }}</h3>
        <p class="text-sm text-gray-400 mt-1">{{ plan.description }}</p>

        <div class="mt-4">
          <span class="text-3xl md:text-4xl font-bold font-mono text-neon">${{ plan.price_mxn }}</span>
          <span class="text-gray-500 text-sm"> MXN</span>
        </div>

        <p class="text-xs text-gray-500 mt-1">
          {{ plan.calls_included }} llamada{{ plan.calls_included > 1 ? 's' : '' }} &middot; hasta {{ plan.max_duration_minutes }} min
        </p>

        <ul class="mt-4 space-y-1.5 flex-1">
          <li v-for="f in (plan.features || [])" :key="f" class="flex items-start gap-2 text-sm text-gray-300">
            <span class="text-neon mt-0.5">&#10003;</span>
            <span>{{ f }}</span>
          </li>
        </ul>

        <button v-if="isCurrentPlan(plan)" disabled
          class="mt-5 w-full py-3 rounded-xl font-bold text-sm bg-matrix-700 text-gray-500 border border-matrix-600 cursor-default">
          Plan actual
        </button>
        <button v-else @click="buy(plan)" :disabled="buying === plan.id"
          class="mt-5 w-full py-3 rounded-xl font-bold text-sm transition"
          :class="plan.is_popular
            ? 'bg-neon text-matrix-900 hover:shadow-neon'
            : 'bg-matrix-700 text-white hover:bg-matrix-600 border border-matrix-600'">
          {{ buying === plan.id ? 'Redirigiendo...' : (isUpgrade(plan) ? 'Upgrade' : 'Comprar') }}
        </button>
      </div>
    </div>

    <!-- Single call purchase -->
    <div v-if="user" class="mt-8 w-full max-w-md">
      <div class="bg-matrix-800 border border-matrix-600 rounded-2xl p-5">
        <h3 class="font-bold text-sm mb-3">Comprar bromas sueltas</h3>
        <p class="text-xs text-gray-400 mb-4">$29 MXN por broma (hasta 3 min)</p>
        <div class="flex items-center gap-3">
          <div class="flex items-center bg-matrix-700 rounded-lg border border-matrix-600">
            <button @click="extraQty = Math.max(1, extraQty - 1)" class="px-3 py-2 text-gray-400 hover:text-white">-</button>
            <span class="px-3 py-2 font-mono font-bold text-neon">{{ extraQty }}</span>
            <button @click="extraQty++" class="px-3 py-2 text-gray-400 hover:text-white">+</button>
          </div>
          <div class="flex-1 text-right">
            <span class="text-lg font-bold font-mono text-neon">${{ extraQty * 29 }}</span>
            <span class="text-gray-500 text-xs"> MXN</span>
          </div>
          <button @click="buyExtra" :disabled="buyingExtra"
            class="px-4 py-2 rounded-lg bg-neon text-matrix-900 font-bold text-sm hover:shadow-neon transition disabled:opacity-50">
            {{ buyingExtra ? '...' : 'Comprar' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Footer links -->
    <div class="mt-8 text-center text-sm text-gray-500">
      <template v-if="!user">
        <router-link to="/" class="hover:text-neon">Prueba gratis</router-link>
        <span class="mx-2">&middot;</span>
        <router-link to="/login" class="hover:text-neon">Iniciar sesion</router-link>
      </template>
      <template v-else>
        <router-link to="/dashboard" class="hover:text-neon">Mis llamadas</router-link>
        <span class="mx-2">&middot;</span>
        <router-link to="/dashboard/new" class="hover:text-neon">Nueva broma</router-link>
      </template>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'

const router = useRouter()
const plans = ref([])
const user = ref(null)
const buying = ref(null)
const extraQty = ref(1)
const buyingExtra = ref(false)

onMounted(async () => {
  try {
    const [p, me] = await Promise.all([
      axios.get('/user-api/plans'),
      axios.get('/user-api/me').catch(() => null),
    ])
    plans.value = p.data
    if (me?.data?.user) user.value = me.data.user
  } catch {}
})

function isCurrentPlan(plan) {
  return user.value?.plan === plan.slug
}

function isUpgrade(plan) {
  if (!user.value?.plan) return false
  const currentPlan = plans.value.find(p => p.slug === user.value.plan)
  return currentPlan && plan.price_mxn > currentPlan.price_mxn
}

async function buy(plan) {
  if (!user.value) { router.push('/login'); return }
  buying.value = plan.id
  try {
    const { data } = await axios.post('/user-api/buy-plan', { plan_id: plan.id })
    window.location.href = data.checkout_url
  } catch (e) {
    if (e.response?.status === 401) router.push('/login')
    buying.value = null
  }
}

async function buyExtra() {
  if (!user.value) { router.push('/login'); return }
  buyingExtra.value = true
  try {
    // Buy N single calls — use the single plan's price N times
    const singlePlan = plans.value.find(p => p.slug === 'single')
    if (!singlePlan) return
    const { data } = await axios.post('/user-api/buy-plan', {
      plan_id: singlePlan.id,
      quantity: extraQty.value,
    })
    window.location.href = data.checkout_url
  } catch (e) {
    if (e.response?.status === 401) router.push('/login')
  } finally { buyingExtra.value = false }
}
</script>
