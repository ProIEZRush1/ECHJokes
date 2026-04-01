<template>
  <div class="p-6 space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold font-mono">Plans</h1>
      <button @click="showCreate = true"
        class="px-4 py-2 bg-neon text-matrix-900 rounded-lg font-bold text-sm hover:shadow-neon transition">
        + New Plan
      </button>
    </div>

    <!-- Plans Grid -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
      <div v-for="plan in plans" :key="plan.id"
        class="bg-matrix-800 border rounded-xl p-5 relative"
        :class="plan.is_popular ? 'border-neon' : 'border-matrix-600'">

        <!-- Popular badge -->
        <div v-if="plan.is_popular" class="absolute -top-3 left-1/2 -translate-x-1/2">
          <span class="px-3 py-0.5 bg-neon text-matrix-900 text-xs font-bold rounded-full">POPULAR</span>
        </div>

        <!-- Status -->
        <div class="flex items-center justify-between mb-3">
          <span class="text-xs font-mono text-gray-500">{{ plan.slug }}</span>
          <span class="px-2 py-0.5 rounded-full text-xs"
            :class="plan.is_active ? 'bg-green-500/20 text-green-400' : 'bg-red-500/20 text-red-400'">
            {{ plan.is_active ? 'Active' : 'Inactive' }}
          </span>
        </div>

        <h3 class="text-xl font-bold">{{ plan.name }}</h3>
        <p class="text-sm text-gray-400 mt-1">{{ plan.description }}</p>

        <!-- Price -->
        <div class="mt-4">
          <span class="text-3xl font-bold font-mono text-neon">${{ plan.price_mxn }}</span>
          <span class="text-gray-500 text-sm"> MXN</span>
        </div>

        <!-- Stats -->
        <div class="mt-3 grid grid-cols-2 gap-2 text-xs">
          <div class="bg-matrix-700 rounded-lg p-2">
            <p class="text-gray-500">Calls</p>
            <p class="font-bold font-mono">{{ plan.calls_included }}</p>
          </div>
          <div class="bg-matrix-700 rounded-lg p-2">
            <p class="text-gray-500">Max duration</p>
            <p class="font-bold font-mono">{{ plan.max_duration_minutes }} min</p>
          </div>
          <div class="bg-matrix-700 rounded-lg p-2 col-span-2">
            <p class="text-gray-500">Price per call</p>
            <p class="font-bold font-mono">${{ (plan.price_mxn / plan.calls_included).toFixed(2) }} MXN</p>
          </div>
        </div>

        <!-- Features -->
        <ul class="mt-4 space-y-1.5">
          <li v-for="f in (plan.features || [])" :key="f" class="flex items-start gap-2 text-xs text-gray-300">
            <span class="text-neon mt-0.5">&#10003;</span>
            <span>{{ f }}</span>
          </li>
        </ul>

        <!-- Actions -->
        <div class="mt-4 pt-3 border-t border-matrix-600 flex gap-2">
          <button @click="editPlan(plan)"
            class="flex-1 py-1.5 text-xs font-medium rounded-lg bg-matrix-700 hover:bg-matrix-600 text-gray-300 transition">
            Edit
          </button>
          <button @click="toggleActive(plan)"
            class="py-1.5 px-3 text-xs font-medium rounded-lg transition"
            :class="plan.is_active ? 'bg-red-500/20 text-red-400 hover:bg-red-500/30' : 'bg-green-500/20 text-green-400 hover:bg-green-500/30'">
            {{ plan.is_active ? 'Disable' : 'Enable' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Create/Edit Modal -->
    <div v-if="showCreate || editing" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" @click.self="closeModal">
      <div class="bg-matrix-800 border border-matrix-600 rounded-2xl p-6 w-full max-w-lg max-h-[90vh] overflow-y-auto">
        <h2 class="text-lg font-bold mb-4">{{ editing ? 'Edit Plan' : 'New Plan' }}</h2>

        <form @submit.prevent="savePlan" class="space-y-3">
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs text-gray-400 uppercase mb-1">Name</label>
              <input v-model="form.name" required class="w-full bg-matrix-700 border border-matrix-600 rounded-lg px-3 py-2 text-sm text-white" />
            </div>
            <div>
              <label class="block text-xs text-gray-400 uppercase mb-1">Slug</label>
              <input v-model="form.slug" :disabled="!!editing" required class="w-full bg-matrix-700 border border-matrix-600 rounded-lg px-3 py-2 text-sm text-white disabled:opacity-50" />
            </div>
          </div>

          <div>
            <label class="block text-xs text-gray-400 uppercase mb-1">Description</label>
            <input v-model="form.description" class="w-full bg-matrix-700 border border-matrix-600 rounded-lg px-3 py-2 text-sm text-white" />
          </div>

          <div class="grid grid-cols-3 gap-3">
            <div>
              <label class="block text-xs text-gray-400 uppercase mb-1">Price (MXN)</label>
              <input v-model.number="form.price_mxn" type="number" step="0.01" required class="w-full bg-matrix-700 border border-matrix-600 rounded-lg px-3 py-2 text-sm text-white" />
            </div>
            <div>
              <label class="block text-xs text-gray-400 uppercase mb-1">Calls included</label>
              <input v-model.number="form.calls_included" type="number" required class="w-full bg-matrix-700 border border-matrix-600 rounded-lg px-3 py-2 text-sm text-white" />
            </div>
            <div>
              <label class="block text-xs text-gray-400 uppercase mb-1">Max minutes</label>
              <input v-model.number="form.max_duration_minutes" type="number" required class="w-full bg-matrix-700 border border-matrix-600 rounded-lg px-3 py-2 text-sm text-white" />
            </div>
          </div>

          <div>
            <label class="block text-xs text-gray-400 uppercase mb-1">Features (one per line)</label>
            <textarea v-model="featuresText" rows="4" class="w-full bg-matrix-700 border border-matrix-600 rounded-lg px-3 py-2 text-sm text-white"></textarea>
          </div>

          <div class="flex gap-4">
            <label class="flex items-center gap-2 text-sm text-gray-300 cursor-pointer">
              <input type="checkbox" v-model="form.is_popular" class="accent-[#39FF14]" /> Popular
            </label>
            <label class="flex items-center gap-2 text-sm text-gray-300 cursor-pointer">
              <input type="checkbox" v-model="form.is_active" class="accent-[#39FF14]" /> Active
            </label>
            <div class="flex items-center gap-2">
              <label class="text-xs text-gray-400">Order:</label>
              <input v-model.number="form.sort_order" type="number" class="w-16 bg-matrix-700 border border-matrix-600 rounded px-2 py-1 text-sm text-white" />
            </div>
          </div>

          <div class="flex gap-2 pt-2">
            <button type="submit" :disabled="saving"
              class="flex-1 py-2.5 rounded-lg bg-neon text-matrix-900 font-bold text-sm hover:shadow-neon transition disabled:opacity-50">
              {{ saving ? 'Saving...' : (editing ? 'Update' : 'Create') }}
            </button>
            <button type="button" @click="closeModal"
              class="px-4 py-2.5 rounded-lg bg-matrix-700 text-gray-300 text-sm hover:bg-matrix-600 transition">
              Cancel
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'

const plans = ref([])
const showCreate = ref(false)
const editing = ref(null)
const saving = ref(false)

const form = ref({
  name: '', slug: '', description: '', price_mxn: 0,
  calls_included: 1, max_duration_minutes: 3,
  is_popular: false, is_active: true, sort_order: 0,
})
const featuresText = ref('')

const features = computed(() => featuresText.value.split('\n').filter(l => l.trim()))

async function fetchPlans() {
  try {
    const { data } = await axios.get('/admin-api/plans')
    plans.value = data
  } catch {}
}

function editPlan(plan) {
  editing.value = plan
  form.value = { ...plan }
  featuresText.value = (plan.features || []).join('\n')
}

function closeModal() {
  showCreate.value = false
  editing.value = null
  form.value = { name: '', slug: '', description: '', price_mxn: 0, calls_included: 1, max_duration_minutes: 3, is_popular: false, is_active: true, sort_order: 0 }
  featuresText.value = ''
}

async function savePlan() {
  saving.value = true
  const payload = { ...form.value, features: features.value }
  try {
    if (editing.value) {
      await axios.put(`/admin-api/plans/${editing.value.id}`, payload)
    } else {
      await axios.post('/admin-api/plans', payload)
    }
    closeModal()
    fetchPlans()
  } catch (e) {
    alert(e.response?.data?.message || 'Error saving plan')
  } finally {
    saving.value = false
  }
}

async function toggleActive(plan) {
  await axios.put(`/admin-api/plans/${plan.id}`, { is_active: !plan.is_active })
  fetchPlans()
}

onMounted(fetchPlans)
</script>
