<template>
  <div class="p-6 lg:p-8 space-y-5 max-w-[1400px]">
    <header class="flex items-end justify-between gap-4 flex-wrap">
      <div>
        <div class="text-[11.5px] uppercase tracking-wider text-gray-500 font-semibold mb-1">Datos</div>
        <h1 class="text-[26px] font-bold tracking-tight">Plans</h1>
        <p class="text-sm text-gray-400 mt-1.5">Paquetes de créditos / suscripciones.</p>
      </div>
      <UiButton variant="primary" @click="openCreate"><Plus class="w-4 h-4" /> Nuevo plan</UiButton>
    </header>

    <AdBanner :slot="AD_SLOTS.plansHeader" format="leaderboard" />

    <div v-if="loading" class="grid grid-cols-1 md:grid-cols-3 gap-5">
      <UiCard v-for="n in 3" :key="n">
        <UiSkeleton h="14px" w="40%" class="mb-3" />
        <UiSkeleton h="22px" w="60%" class="mb-2" />
        <UiSkeleton h="32px" w="50%" />
      </UiCard>
    </div>

    <UiEmptyState
      v-else-if="!plans.length"
      title="Aún no hay planes"
      body="Crea tu primer plan de créditos."
      :icon="CreditCard"
    >
      <template #action><UiButton variant="primary" @click="openCreate"><Plus class="w-4 h-4" /> Crear plan</UiButton></template>
    </UiEmptyState>

    <div v-else class="grid grid-cols-1 md:grid-cols-3 gap-5">
      <UiCard
        v-for="(plan, i) in plans"
        :key="plan.id"
        hover
        :padded="false"
        class="relative animate-[fade-in-up_0.4s_ease-out_both]"
        :style="{ animationDelay: (i * 50) + 'ms' }"
        :class="plan.is_popular ? 'shadow-[0_0_0_1px_var(--color-neon),0_22px_52px_-22px_rgba(57,255,20,0.4)]' : ''"
      >
        <!-- Popular ribbon -->
        <div v-if="plan.is_popular" class="absolute -top-3 left-1/2 -translate-x-1/2">
          <span class="px-3 py-0.5 bg-neon text-matrix-900 text-[10.5px] font-bold rounded-full uppercase tracking-wider animate-[pulse-neon_2s_ease-in-out_infinite]">Popular</span>
        </div>

        <div class="p-5">
          <div class="flex items-center justify-between mb-3">
            <span class="text-[11px] font-mono text-gray-500">{{ plan.slug }}</span>
            <UiBadge
              :status="''"
              :color="plan.is_active ? '#39FF14' : '#ff6b6b'"
              :dot="false"
            >{{ plan.is_active ? 'Active' : 'Inactive' }}</UiBadge>
          </div>

          <h3 class="text-xl font-bold tracking-tight">{{ plan.name }}</h3>
          <p class="text-sm text-gray-400 mt-1 min-h-[20px]">{{ plan.description }}</p>

          <div class="mt-4 flex items-baseline gap-1.5">
            <span class="text-3xl font-bold font-mono text-neon" style="text-shadow:0 0 18px rgba(57,255,20,0.4)">${{ plan.price_mxn }}</span>
            <span class="text-gray-500 text-sm">MXN</span>
          </div>

          <div class="mt-3 grid grid-cols-2 gap-2 text-xs">
            <div class="bg-white/5 border border-white/5 rounded-lg p-2">
              <p class="text-gray-500 text-[10.5px] uppercase tracking-wide">Calls</p>
              <p class="font-bold font-mono text-base">{{ plan.calls_included }}</p>
            </div>
            <div class="bg-white/5 border border-white/5 rounded-lg p-2">
              <p class="text-gray-500 text-[10.5px] uppercase tracking-wide">Max dur</p>
              <p class="font-bold font-mono text-base">{{ plan.max_duration_minutes }} min</p>
            </div>
            <div class="bg-white/5 border border-white/5 rounded-lg p-2 col-span-2">
              <p class="text-gray-500 text-[10.5px] uppercase tracking-wide">Por llamada</p>
              <p class="font-bold font-mono text-sm">${{ (plan.price_mxn / plan.calls_included).toFixed(2) }} MXN</p>
            </div>
          </div>

          <ul v-if="(plan.features || []).length" class="mt-4 space-y-1.5">
            <li v-for="f in plan.features" :key="f" class="flex items-start gap-2 text-xs text-gray-300">
              <Check class="w-3.5 h-3.5 text-neon mt-0.5 flex-shrink-0" />
              <span>{{ f }}</span>
            </li>
          </ul>

          <div class="mt-4 pt-3 border-t border-white/5 flex gap-2">
            <UiButton variant="secondary" size="sm" class="flex-1" @click="editPlan(plan)">
              <Pencil class="w-3 h-3" /> Edit
            </UiButton>
            <UiButton :variant="plan.is_active ? 'danger' : 'neonGhost'" size="sm" @click="toggleActive(plan)">
              {{ plan.is_active ? 'Disable' : 'Enable' }}
            </UiButton>
          </div>
        </div>
      </UiCard>
    </div>

    <UiModal v-model="modalOpen" :title="editing ? 'Editar plan' : 'Nuevo plan'" size="md">
      <form @submit.prevent="savePlan" class="space-y-4">
        <div class="grid grid-cols-2 gap-3">
          <UiInput v-model="form.name" label="Name" required />
          <UiInput v-model="form.slug" label="Slug" :disabled="!!editing" required />
        </div>
        <UiInput v-model="form.description" label="Description" />

        <div class="grid grid-cols-3 gap-3">
          <UiInput v-model.number="form.price_mxn" label="Price (MXN)" type="number" required />
          <UiInput v-model.number="form.calls_included" label="Calls included" type="number" required />
          <UiInput v-model.number="form.max_duration_minutes" label="Max minutes" type="number" required />
        </div>

        <UiTextarea v-model="featuresText" label="Features (one per line)" :rows="4" />

        <div class="flex flex-wrap gap-4 items-center pt-1">
          <label class="flex items-center gap-2 text-sm text-gray-300 cursor-pointer">
            <input type="checkbox" v-model="form.is_popular" class="accent-[#39FF14]" /> Popular
          </label>
          <label class="flex items-center gap-2 text-sm text-gray-300 cursor-pointer">
            <input type="checkbox" v-model="form.is_active" class="accent-[#39FF14]" /> Active
          </label>
          <div class="flex items-center gap-2">
            <span class="text-xs text-gray-400">Order:</span>
            <input v-model.number="form.sort_order" type="number" class="w-16 bg-white/5 border border-white/10 rounded px-2 py-1 text-sm text-white focus:outline-none focus:border-neon/50" />
          </div>
        </div>
      </form>

      <template #footer>
        <UiButton variant="ghost" @click="modalOpen = false">Cancelar</UiButton>
        <UiButton variant="primary" :loading="saving" @click="savePlan">
          {{ editing ? 'Actualizar' : 'Crear' }}
        </UiButton>
      </template>
    </UiModal>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import axios from 'axios'
import { Plus, Pencil, Check, CreditCard } from 'lucide-vue-next'

import AdBanner from '../../components/AdBanner.vue'
import { AD_SLOTS } from '../../adsense.js'
import UiCard from '../components/UiCard.vue'
import UiButton from '../components/UiButton.vue'
import UiBadge from '../components/UiBadge.vue'
import UiSkeleton from '../components/UiSkeleton.vue'
import UiEmptyState from '../components/UiEmptyState.vue'
import UiModal from '../components/UiModal.vue'
import UiInput from '../components/UiInput.vue'
import UiTextarea from '../components/UiTextarea.vue'
import { useToast } from '../composables/useToast.js'

const toast = useToast()

const plans = ref([])
const loading = ref(true)
const modalOpen = ref(false)
const editing = ref(null)
const saving = ref(false)

const form = ref(blankForm())
const featuresText = ref('')

function blankForm() {
  return {
    name: '', slug: '', description: '', price_mxn: 0,
    calls_included: 1, max_duration_minutes: 3,
    is_popular: false, is_active: true, sort_order: 0,
  }
}

const features = computed(() => featuresText.value.split('\n').map(s => s.trim()).filter(Boolean))

async function fetchPlans() {
  loading.value = true
  try {
    const { data } = await axios.get('/admin-api/plans')
    plans.value = data
  } catch (e) { toast.error(e) } finally { loading.value = false }
}

function openCreate() {
  editing.value = null
  form.value = blankForm()
  featuresText.value = ''
  modalOpen.value = true
}

function editPlan(plan) {
  editing.value = plan
  form.value = { ...blankForm(), ...plan }
  featuresText.value = (plan.features || []).join('\n')
  modalOpen.value = true
}

async function savePlan() {
  saving.value = true
  const payload = { ...form.value, features: features.value }
  try {
    if (editing.value) await axios.put(`/admin-api/plans/${editing.value.id}`, payload)
    else await axios.post('/admin-api/plans', payload)
    toast.success(editing.value ? 'Plan actualizado' : 'Plan creado')
    modalOpen.value = false
    fetchPlans()
  } catch (e) { toast.error(e) } finally { saving.value = false }
}

async function toggleActive(plan) {
  try {
    await axios.put(`/admin-api/plans/${plan.id}`, { is_active: !plan.is_active })
    plan.is_active = !plan.is_active
    toast.success(plan.is_active ? 'Plan activado' : 'Plan desactivado')
  } catch (e) { toast.error(e) }
}

watch(modalOpen, (v) => { if (!v) editing.value = null })

onMounted(fetchPlans)
</script>
