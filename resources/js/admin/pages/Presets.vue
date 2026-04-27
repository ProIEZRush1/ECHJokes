<template>
  <div class="p-4 md:p-6 space-y-6">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
      <h1 class="text-2xl font-bold font-mono">Presets</h1>
      <button @click="openCreate"
        class="px-4 py-2 bg-neon text-matrix-900 rounded-lg font-bold text-sm hover:shadow-neon transition">
        + New Preset
      </button>
    </div>

    <div v-if="loading" class="text-center py-16 text-gray-500">
      <span class="inline-block w-6 h-6 border-2 border-neon border-t-transparent rounded-full animate-spin align-middle mr-2"></span>
      Cargando presets...
    </div>

    <div v-else-if="!presets.length" class="text-center py-16 text-gray-500">Aún no hay presets.</div>

    <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
      <div v-for="p in presets" :key="p.id"
        class="bg-matrix-800 border border-matrix-600 rounded-xl p-4 relative">
        <div class="flex items-start justify-between mb-2">
          <div class="flex items-center gap-2">
            <span class="text-2xl">{{ p.emoji }}</span>
            <div>
              <h3 class="font-semibold text-sm">{{ p.label }}</h3>
              <span class="text-[10px] px-1.5 py-0.5 rounded bg-matrix-700 text-gray-400">{{ p.category }}</span>
            </div>
          </div>
          <span class="text-xs" :class="p.is_active ? 'text-neon' : 'text-gray-600'">
            {{ p.is_active ? 'ON' : 'OFF' }}
          </span>
        </div>

        <p class="text-xs text-gray-400 line-clamp-3 mb-2">{{ p.scenario }}</p>

        <div class="flex items-center gap-2 text-[10px] text-gray-500">
          <span>{{ p.voice === 'ash' ? '👨' : '👩' }} {{ p.voice }}</span>
          <span v-if="p.character">&middot; {{ p.character?.substring(0, 30) }}</span>
        </div>

        <div class="flex gap-2 mt-3 pt-2 border-t border-matrix-700">
          <button @click="edit(p)" class="flex-1 py-1 text-xs rounded bg-matrix-700 hover:bg-matrix-600 transition">Edit</button>
          <button @click="toggle(p)" class="py-1 px-2 text-xs rounded transition"
            :class="p.is_active ? 'bg-red-500/20 text-red-400' : 'bg-green-500/20 text-green-400'">
            {{ p.is_active ? 'Off' : 'On' }}
          </button>
          <button @click="del(p)" class="py-1 px-2 text-xs rounded bg-red-500/20 text-red-400 hover:bg-red-500/30 transition">Del</button>
        </div>
      </div>
    </div>

    <!-- Modal -->
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" @click.self="showModal = false">
      <div class="bg-matrix-800 border border-matrix-600 rounded-2xl p-5 w-full max-w-lg max-h-[90vh] overflow-y-auto">
        <h2 class="text-lg font-bold mb-4">{{ editing ? 'Edit' : 'New' }} Preset</h2>
        <form @submit.prevent="save" class="space-y-3">
          <div class="grid grid-cols-4 gap-3">
            <div class="col-span-1">
              <label class="block text-xs text-gray-400 uppercase mb-1">Emoji</label>
              <input v-model="form.emoji" class="w-full bg-matrix-700 border border-matrix-600 rounded-lg px-3 py-2 text-2xl text-center" />
            </div>
            <div class="col-span-3">
              <label class="block text-xs text-gray-400 uppercase mb-1">Label</label>
              <input v-model="form.label" required class="w-full bg-matrix-700 border border-matrix-600 rounded-lg px-3 py-2 text-sm text-white" />
            </div>
          </div>

          <div>
            <label class="block text-xs text-gray-400 uppercase mb-1">Scenario</label>
            <textarea v-model="form.scenario" required rows="4" class="w-full bg-matrix-700 border border-matrix-600 rounded-lg px-3 py-2 text-sm text-white resize-none"></textarea>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs text-gray-400 uppercase mb-1">Character</label>
              <input v-model="form.character" class="w-full bg-matrix-700 border border-matrix-600 rounded-lg px-3 py-2 text-sm text-white" />
            </div>
            <div>
              <label class="block text-xs text-gray-400 uppercase mb-1">Category</label>
              <input v-model="form.category" class="w-full bg-matrix-700 border border-matrix-600 rounded-lg px-3 py-2 text-sm text-white" />
            </div>
          </div>

          <div>
            <label class="block text-xs text-gray-400 uppercase mb-1">Style</label>
            <input v-model="form.style" placeholder="Ej: Formal y serio, Chistoso, Nervioso..."
              class="w-full bg-matrix-700 border border-matrix-600 rounded-lg px-3 py-2 text-sm text-white" />
          </div>

          <div class="flex items-center gap-4">
            <div class="flex gap-2">
              <button type="button" @click="form.voice = 'ash'"
                :class="['px-3 py-1.5 rounded-lg text-xs transition', form.voice === 'ash' ? 'bg-neon/20 text-neon border border-neon/30' : 'bg-matrix-700 text-gray-400']">
                👨 Ash
              </button>
              <button type="button" @click="form.voice = 'coral'"
                :class="['px-3 py-1.5 rounded-lg text-xs transition', form.voice === 'coral' ? 'bg-neon/20 text-neon border border-neon/30' : 'bg-matrix-700 text-gray-400']">
                👩 Coral
              </button>
            </div>
            <label class="flex items-center gap-1.5 text-xs text-gray-300 cursor-pointer">
              <input type="checkbox" v-model="form.is_active" class="accent-[#39FF14]" /> Active
            </label>
            <div class="flex items-center gap-1.5">
              <span class="text-xs text-gray-400">Order:</span>
              <input v-model.number="form.sort_order" type="number" class="w-14 bg-matrix-700 border border-matrix-600 rounded px-2 py-1 text-xs text-white" />
            </div>
          </div>

          <div class="flex gap-2 pt-2">
            <button type="submit" class="flex-1 py-2 rounded-lg bg-neon text-matrix-900 font-bold text-sm hover:shadow-neon transition">
              {{ editing ? 'Update' : 'Create' }}
            </button>
            <button type="button" @click="showModal = false" class="px-4 py-2 rounded-lg bg-matrix-700 text-gray-300 text-sm">Cancel</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

const presets = ref([])
const loading = ref(true)
const showModal = ref(false)
const editing = ref(null)
const form = ref({ label: '', emoji: '🎭', scenario: '', character: '', voice: 'ash', category: 'general', is_active: true, sort_order: 0 })

async function fetch() {
  loading.value = true
  try { const { data } = await axios.get('/admin-api/presets'); presets.value = data }
  catch {} finally { loading.value = false }
}

function openCreate() {
  editing.value = null
  form.value = { label: '', emoji: '🎭', scenario: '', character: '', voice: 'ash', style: '', category: 'general', is_active: true, sort_order: 0 }
  showModal.value = true
}

function edit(p) { editing.value = p; form.value = { ...p }; showModal.value = true }

async function save() {
  if (editing.value) await axios.put(`/admin-api/presets/${editing.value.id}`, form.value)
  else await axios.post('/admin-api/presets', form.value)
  showModal.value = false; fetch()
}

async function toggle(p) { await axios.put(`/admin-api/presets/${p.id}`, { is_active: !p.is_active }); fetch() }
async function del(p) { if (confirm('Delete?')) { await axios.delete(`/admin-api/presets/${p.id}`); fetch() } }

onMounted(fetch)
</script>
