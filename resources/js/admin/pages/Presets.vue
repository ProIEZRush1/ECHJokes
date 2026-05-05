<template>
  <div class="p-6 lg:p-8 space-y-5 max-w-[1400px]">
    <header class="flex items-end justify-between gap-4 flex-wrap">
      <div>
        <div class="text-[11.5px] uppercase tracking-wider text-gray-500 font-semibold mb-1">Datos</div>
        <h1 class="text-[26px] font-bold tracking-tight">Presets</h1>
        <p class="text-sm text-gray-400 mt-1.5">Escenarios reutilizables para Launch Call.</p>
      </div>
      <UiButton variant="primary" @click="openCreate">
        <Plus class="w-4 h-4" /> Nuevo preset
      </UiButton>
    </header>

    <AdBanner :slot="AD_SLOTS.presetsHeader" format="leaderboard" />

    <div v-if="loading" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
      <UiCard v-for="n in 6" :key="n">
        <UiSkeleton h="20px" w="60%" class="mb-2" />
        <UiSkeleton h="14px" w="80%" class="mb-1" />
        <UiSkeleton h="14px" w="40%" />
      </UiCard>
    </div>

    <UiEmptyState
      v-else-if="!presets.length"
      title="Aún no hay presets"
      body="Los presets son escenarios listos para reusar en Launch Call. Crea el primero."
      :icon="Theater"
    >
      <template #action>
        <UiButton variant="primary" @click="openCreate"><Plus class="w-4 h-4" /> Crear preset</UiButton>
      </template>
    </UiEmptyState>

    <div v-else ref="gridEl" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
      <UiCard
        v-for="(p, i) in presets"
        :key="p.id"
        hover
        :padded="false"
        class="animate-[fade-in-up_0.35s_ease-out_both]"
        :style="{ animationDelay: (i * 30) + 'ms' }"
      >
        <div class="p-4">
          <div class="flex items-start justify-between mb-2">
            <div class="flex items-center gap-3">
              <span class="text-2xl leading-none">{{ p.emoji }}</span>
              <div>
                <h3 class="font-semibold text-[14px] text-white">{{ p.label }}</h3>
                <span class="inline-block mt-0.5 text-[10.5px] px-1.5 py-0.5 rounded bg-white/5 text-gray-400">{{ p.category }}</span>
              </div>
            </div>
            <UiBadge
              :status="''"
              :color="p.is_active ? '#39FF14' : '#666'"
              :dot="true"
              :pulse="false"
            >{{ p.is_active ? 'ON' : 'OFF' }}</UiBadge>
          </div>

          <p class="text-xs text-gray-400 line-clamp-3 mb-2">{{ p.scenario }}</p>

          <div class="flex items-center gap-2 text-[10.5px] text-gray-500">
            <span>{{ p.voice === 'ash' ? '👨' : '👩' }} {{ p.voice }}</span>
            <span v-if="p.character" class="truncate">· {{ p.character?.substring(0, 40) }}</span>
          </div>

          <div class="flex gap-2 mt-3 pt-2 border-t border-white/5">
            <UiButton variant="secondary" size="sm" class="flex-1" @click="edit(p)">
              <Pencil class="w-3 h-3" /> Edit
            </UiButton>
            <UiButton :variant="p.is_active ? 'danger' : 'neonGhost'" size="sm" @click="toggle(p)">
              {{ p.is_active ? 'Off' : 'On' }}
            </UiButton>
            <UiButton variant="danger" size="sm" @click="del(p)">
              <Trash2 class="w-3 h-3" />
            </UiButton>
          </div>
        </div>
      </UiCard>
    </div>

    <!-- Modal -->
    <UiModal v-model="showModal" :title="editing ? 'Editar preset' : 'Nuevo preset'" size="md">
      <form @submit.prevent="save" class="space-y-4">
        <div class="grid grid-cols-4 gap-3">
          <div class="col-span-1">
            <UiInput v-model="form.emoji" label="Emoji" />
          </div>
          <div class="col-span-3">
            <UiInput v-model="form.label" label="Label" required />
          </div>
        </div>

        <UiTextarea v-model="form.scenario" label="Scenario" :rows="4" required />

        <div class="grid grid-cols-2 gap-3">
          <UiInput v-model="form.character" label="Character" />
          <UiInput v-model="form.category" label="Category" />
        </div>

        <UiInput v-model="form.style" label="Style" placeholder="Ej: Formal y serio, Chistoso, Nervioso…" />

        <div class="flex flex-wrap items-center gap-4">
          <div class="flex gap-2">
            <UiButton type="button" :variant="form.voice === 'ash' ? 'neonGhost' : 'secondary'" size="sm" @click="form.voice = 'ash'">
              👨 Ash
            </UiButton>
            <UiButton type="button" :variant="form.voice === 'coral' ? 'neonGhost' : 'secondary'" size="sm" @click="form.voice = 'coral'">
              👩 Coral
            </UiButton>
          </div>
          <label class="flex items-center gap-1.5 text-xs text-gray-300 cursor-pointer">
            <input type="checkbox" v-model="form.is_active" class="accent-[#39FF14]" /> Active
          </label>
          <div class="flex items-center gap-1.5">
            <span class="text-xs text-gray-400">Order:</span>
            <input v-model.number="form.sort_order" type="number" class="w-16 bg-white/5 border border-white/10 rounded px-2 py-1 text-xs text-white focus:outline-none focus:border-neon/50" />
          </div>
        </div>
      </form>

      <template #footer>
        <UiButton variant="ghost" @click="showModal = false">Cancelar</UiButton>
        <UiButton variant="primary" :loading="saving" @click="save">
          {{ editing ? 'Actualizar' : 'Crear' }}
        </UiButton>
      </template>
    </UiModal>
  </div>
</template>

<script setup>
import { ref, onMounted, watch, nextTick } from 'vue'
import axios from 'axios'
import { autoAnimate } from '@formkit/auto-animate'
import { Plus, Pencil, Trash2, Theater } from 'lucide-vue-next'

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
import { useConfirm } from '../composables/useConfirm.js'

const toast = useToast()
const confirm = useConfirm()

const presets = ref([])
const loading = ref(true)
const showModal = ref(false)
const editing = ref(null)
const saving = ref(false)
const gridEl = ref(null)
const form = ref(blankForm())

function blankForm() {
  return { label: '', emoji: '🎭', scenario: '', character: '', voice: 'ash', style: '', category: 'general', is_active: true, sort_order: 0 }
}

async function fetchPresets() {
  loading.value = true
  try {
    const { data } = await axios.get('/admin-api/presets')
    presets.value = data
    await nextTick()
    if (gridEl.value && !gridEl.value._aa) { autoAnimate(gridEl.value); gridEl.value._aa = true }
  } catch (e) { toast.error(e) } finally { loading.value = false }
}

function openCreate() {
  editing.value = null
  form.value = blankForm()
  showModal.value = true
}
function edit(p) {
  editing.value = p
  form.value = { ...blankForm(), ...p }
  showModal.value = true
}

async function save() {
  saving.value = true
  try {
    if (editing.value) await axios.put(`/admin-api/presets/${editing.value.id}`, form.value)
    else await axios.post('/admin-api/presets', form.value)
    toast.success(editing.value ? 'Preset actualizado' : 'Preset creado')
    showModal.value = false
    fetchPresets()
  } catch (e) { toast.error(e) } finally { saving.value = false }
}

async function toggle(p) {
  try {
    await axios.put(`/admin-api/presets/${p.id}`, { is_active: !p.is_active })
    p.is_active = !p.is_active
    toast.success(p.is_active ? 'Activado' : 'Desactivado')
  } catch (e) { toast.error(e) }
}

async function del(p) {
  if (!await confirm({ title: '¿Borrar preset?', body: `Vas a eliminar "${p.label}". No se puede deshacer.`, danger: true })) return
  try {
    await axios.delete(`/admin-api/presets/${p.id}`)
    presets.value = presets.value.filter(x => x.id !== p.id)
    toast.success('Preset eliminado')
  } catch (e) { toast.error(e) }
}

watch(showModal, (v) => { if (!v) editing.value = null })

onMounted(fetchPresets)
</script>
