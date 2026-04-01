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
        :class="plan.is_popular && !isCurrentPlan(plan) ? 'border-neon md:scale-105' : (isCurrentPlan(plan) ? 'border-blue-500/50' : 'border-matrix-600')">

        <div v-if="plan.is_popular && !isCurrentPlan(plan)" class="absolute -top-3 left-1/2 -translate-x-1/2">
          <span class="px-3 py-0.5 bg-neon text-matrix-900 text-xs font-bold rounded-full whitespace-nowrap">MAS POPULAR</span>
        </div>
        <div v-if="isCurrentPlan(plan)" class="absolute -top-3 left-1/2 -translate-x-1/2">
          <span class="px-3 py-0.5 bg-blue-500 text-white text-xs font-bold rounded-full whitespace-nowrap">TU PLAN</span>
        </div>

        <h3 class="text-xl font-bold">{{ plan.name }}</h3>
        <p class="text-sm text-gray-400 mt-1">{{ plan.description }}</p>

        <div class="mt-4">
          <!-- Show upgrade price if applicable -->
          <template v-if="upgradeDiscount(plan) > 0">
            <span class="text-lg text-gray-500 line-through mr-2">${{ plan.price_mxn }}</span>
            <span class="text-3xl md:text-4xl font-bold font-mono text-neon">${{ (plan.price_mxn - upgradeDiscount(plan)).toFixed(0) }}</span>
          </template>
          <template v-else>
            <span class="text-3xl md:text-4xl font-bold font-mono text-neon">${{ plan.price_mxn }}</span>
          </template>
          <span class="text-gray-500 text-sm"> MXN</span>
        </div>

        <p v-if="upgradeDiscount(plan) > 0" class="text-xs text-blue-400 mt-1">
          Descuento de ${{ upgradeDiscount(plan).toFixed(0) }} MXN por tu plan actual
        </p>

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
          class="mt-5 w-full py-3 rounded-xl font-bold text-sm bg-matrix-700 text-gray-500 border border-matrix-600">
          Plan actual
        </button>
        <button v-else-if="isDowngrade(plan)" disabled
          class="mt-5 w-full py-3 rounded-xl font-bold text-sm bg-matrix-700 text-gray-600 border border-matrix-600">
          Ya tienes un plan superior
        </button>
        <button v-else @click="buy(plan)" :disabled="buying === plan.id"
          class="mt-5 w-full py-3 rounded-xl font-bold text-sm transition"
          :class="plan.is_popular || upgradeDiscount(plan) > 0
            ? 'bg-neon text-matrix-900 hover:shadow-neon'
            : 'bg-matrix-700 text-white hover:bg-matrix-600 border border-matrix-600'">
          {{ buying === plan.id ? 'Redirigiendo...' : (upgradeDiscount(plan) > 0 ? 'Upgrade' : 'Comprar') }}
        </button>
      </div>
    </div>

    <!-- Custom purchase -->
    <div class="mt-10 w-full max-w-lg">
      <div class="bg-matrix-800 border border-matrix-600 rounded-2xl p-5 md:p-6">
        <h3 class="font-bold text-lg mb-1">Compra personalizada</h3>
        <p class="text-xs text-gray-400 mb-5">Elige cuantas bromas y de cuantos minutos quieres</p>

        <div class="grid grid-cols-2 gap-4 mb-4">
          <div>
            <label class="block text-xs text-gray-500 uppercase mb-1.5">Cantidad de bromas</label>
            <div class="flex items-center bg-matrix-700 rounded-xl border border-matrix-600">
              <button @click="customCalls = Math.max(1, customCalls - 1)" class="px-4 py-2.5 text-gray-400 hover:text-white text-lg">-</button>
              <span class="flex-1 text-center font-mono font-bold text-lg text-neon">{{ customCalls }}</span>
              <button @click="customCalls = Math.min(50, customCalls + 1)" class="px-4 py-2.5 text-gray-400 hover:text-white text-lg">+</button>
            </div>
          </div>
          <div>
            <label class="block text-xs text-gray-500 uppercase mb-1.5">Minutos por broma</label>
            <div class="flex items-center bg-matrix-700 rounded-xl border border-matrix-600">
              <button @click="customMinutes = Math.max(1, customMinutes - 1)" class="px-4 py-2.5 text-gray-400 hover:text-white text-lg">-</button>
              <span class="flex-1 text-center font-mono font-bold text-lg text-neon">{{ customMinutes }}</span>
              <button @click="customMinutes = Math.min(10, customMinutes + 1)" class="px-4 py-2.5 text-gray-400 hover:text-white text-lg">+</button>
            </div>
          </div>
        </div>

        <!-- Price breakdown -->
        <div class="bg-matrix-700 rounded-xl p-4 mb-4 space-y-2 text-sm">
          <div class="flex justify-between text-gray-400">
            <span>Precio por broma ({{ customMinutes }} min)</span>
            <span class="font-mono">${{ customPerCall }} MXN</span>
          </div>
          <div class="flex justify-between text-gray-400">
            <span>{{ customCalls }} broma{{ customCalls > 1 ? 's' : '' }}</span>
            <span class="font-mono">x{{ customCalls }}</span>
          </div>
          <div class="border-t border-matrix-600 pt-2 flex justify-between font-bold">
            <span>Total</span>
            <span class="text-neon font-mono text-lg">${{ customTotal }} MXN</span>
          </div>
        </div>

        <button @click="buyCustom" :disabled="buyingCustom"
          class="w-full py-3 rounded-xl bg-neon text-matrix-900 font-bold hover:shadow-neon transition disabled:opacity-50">
          {{ buyingCustom ? 'Redirigiendo...' : 'Comprar' }}
        </button>
      </div>
    </div>

    <!-- Footer -->
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
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'

const router = useRouter()
const plans = ref([])
const user = ref(null)
const buying = ref(null)
const buyingCustom = ref(false)

// Custom purchase
const customCalls = ref(3)
const customMinutes = ref(3)

const customPerCall = computed(() => {
  const base = 18
  const extra = 7
  return base + Math.max(0, (customMinutes.value - 3) * extra)
})

const customTotal = computed(() => customPerCall.value * customCalls.value)

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

function isDowngrade(plan) {
  if (!user.value?.plan) return false
  const current = plans.value.find(p => p.slug === user.value.plan)
  return current && plan.price_mxn < current.price_mxn
}

function upgradeDiscount(plan) {
  if (!user.value?.plan || isCurrentPlan(plan) || isDowngrade(plan)) return 0
  const current = plans.value.find(p => p.slug === user.value.plan)
  if (!current || plan.price_mxn <= current.price_mxn) return 0
  // Discount = unused value from current plan
  const usedCalls = 0 // We don't have this client-side, server calculates exact
  return Math.max(0, current.price_mxn) // Show max possible discount, server calculates exact
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

async function buyCustom() {
  if (!user.value) { router.push('/login'); return }
  buyingCustom.value = true
  try {
    const { data } = await axios.post('/user-api/buy-custom', {
      calls: customCalls.value,
      minutes: customMinutes.value,
    })
    window.location.href = data.checkout_url
  } catch (e) {
    if (e.response?.status === 401) router.push('/login')
  } finally { buyingCustom.value = false }
}
</script>
