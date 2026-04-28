<template>
  <div v-if="data" class="p-6 lg:p-8 space-y-5 max-w-[1400px]">
    <!-- Hero header -->
    <header class="flex items-center gap-4">
      <UiButton variant="ghost" size="sm" @click="$router.push('/admin/users')">
        <ArrowLeft class="w-4 h-4" /> Users
      </UiButton>
      <Avatar :name="data.user.name" size="lg" />
      <div class="flex-1 min-w-0">
        <div class="flex items-center gap-2">
          <h1 class="text-[24px] font-bold tracking-tight truncate">{{ data.user.name }}</h1>
          <UiBadge v-if="data.user.is_admin" :status="''" :color="'#39FF14'" :dot="false">Admin</UiBadge>
        </div>
        <p class="text-sm text-gray-400 truncate">{{ data.user.email }}</p>
      </div>
    </header>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
      <!-- Left: profile + actions -->
      <div class="space-y-4">
        <UiCard title="Info">
          <dl class="space-y-2 text-sm">
            <div class="flex justify-between"><dt class="text-gray-500">Phone</dt><dd>{{ data.user.phone || '—' }}</dd></div>
            <div class="flex justify-between"><dt class="text-gray-500">Joined</dt><dd>{{ formatDate(data.user.created_at) }}</dd></div>
            <div class="flex justify-between"><dt class="text-gray-500">Plan</dt><dd class="text-neon font-mono">{{ data.user.subscription_plan || 'None' }}</dd></div>
            <div class="flex justify-between"><dt class="text-gray-500">Credits</dt><dd class="text-neon font-mono text-base">{{ data.credits }}</dd></div>
            <div class="flex justify-between">
              <dt class="text-gray-500">Chistes</dt>
              <dd class="font-mono text-neon text-base">{{ data.jokes }}
                <span v-if="data.jokes_reset_at" class="text-[10px] text-gray-500 ml-1">reset {{ formatDate(data.jokes_reset_at) }}</span>
              </dd>
            </div>
          </dl>
        </UiCard>

        <UiCard title="Call stats">
          <div class="grid grid-cols-3 gap-2 text-center">
            <div class="bg-white/5 rounded-lg p-2">
              <p class="text-lg font-bold font-mono">{{ data.call_stats.total }}</p>
              <p class="text-[10px] text-gray-500 uppercase">Total</p>
            </div>
            <div class="bg-white/5 rounded-lg p-2">
              <p class="text-lg font-bold font-mono text-neon">{{ data.call_stats.completed }}</p>
              <p class="text-[10px] text-gray-500 uppercase">Completed</p>
            </div>
            <div class="bg-white/5 rounded-lg p-2">
              <p class="text-lg font-bold font-mono text-blue-400">{{ data.call_stats.paid }}</p>
              <p class="text-[10px] text-gray-500 uppercase">Paid</p>
            </div>
          </div>
        </UiCard>

        <UiCard v-if="data.stripe" title="Stripe">
          <div v-if="data.stripe.error" class="text-xs text-gray-500">{{ data.stripe.error }}</div>
          <template v-else>
            <p class="text-xs text-gray-500 mb-2 font-mono">{{ data.stripe.customer_id }}</p>
            <div v-for="card in data.stripe.cards" :key="card.last4"
              class="flex items-center gap-2 bg-white/5 rounded-lg p-2 mb-1.5">
              <CreditCard class="w-4 h-4 text-gray-400" />
              <span class="text-sm font-bold uppercase">{{ card.brand }}</span>
              <span class="font-mono text-sm">****{{ card.last4 }}</span>
              <span class="text-xs text-gray-500 ml-auto">{{ card.exp_month }}/{{ card.exp_year }}</span>
            </div>
            <p v-if="!data.stripe.cards?.length" class="text-xs text-gray-500">No cards on file</p>
          </template>
        </UiCard>

        <UiCard title="Admin actions">
          <div class="space-y-3">
            <div class="flex gap-2 items-end">
              <UiInput v-model.number="editCredits" label="Credits" type="number" class="flex-1" />
              <UiButton variant="primary" size="md" @click="setCredits">Set</UiButton>
            </div>
            <div class="flex gap-2 items-end">
              <UiInput v-model.number="editJokes" label="Chistes" type="number" class="flex-1" />
              <UiButton variant="primary" size="md" @click="setJokes">Set</UiButton>
            </div>
            <div class="flex gap-2 items-end">
              <UiSelect
                v-model="editPlan"
                label="Plan (no charge)"
                :options="[{ value: '', label: 'None' }, ...plans.map(p => ({ value: p.slug, label: `${p.name} (${p.calls_included})` }))]"
                class="flex-1"
              />
              <UiButton variant="primary" size="md" @click="setPlan">Set</UiButton>
            </div>
            <label class="flex items-center gap-2 text-sm text-gray-300 cursor-pointer pt-1">
              <input type="checkbox" v-model="editAdmin" @change="setAdmin" class="accent-[#39FF14]" />
              Admin access
            </label>
          </div>
        </UiCard>

        <UiCard v-if="!data.user.is_admin" class="border-red-500/30">
          <h2 class="text-sm font-semibold text-red-400 uppercase mb-2">Danger zone</h2>
          <p class="text-xs text-gray-400 mb-3">Elimina al usuario, sus créditos, llamadas y referidos. No se puede deshacer.</p>
          <UiButton variant="danger" :loading="deleting" @click="deleteUser" class="w-full">
            <Trash2 class="w-4 h-4" /> Eliminar usuario completamente
          </UiButton>
        </UiCard>
      </div>

      <!-- Right: recent calls -->
      <div class="lg:col-span-2">
        <UiCard title="Recent calls" :padded="false">
          <UiTable
            :columns="callColumns"
            :rows="data.calls"
            empty-title="Sin llamadas"
            @row-click="(c) => $router.push('/admin/calls/' + c.id)"
          >
            <template #cell-phone_number="{ value }">
              <PhoneCell :phone="value" :mask="false" class="group" />
            </template>
            <template #cell-custom_joke_prompt="{ value }">
              <span class="text-gray-300 line-clamp-1">{{ value || '—' }}</span>
            </template>
            <template #cell-status="{ value }"><UiBadge :status="value" /></template>
            <template #cell-call_duration_seconds="{ value }">
              <span class="font-mono text-[13px]">{{ formatDuration(value) }}</span>
            </template>
            <template #cell-created_at="{ value }">
              <span class="text-gray-500 text-xs">{{ formatDateTime(value) }}</span>
            </template>
          </UiTable>
        </UiCard>
      </div>
    </div>
  </div>

  <div v-else class="p-8 flex items-center justify-center min-h-[50vh]">
    <UiSpinner :size="20" />
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import axios from 'axios'
import { ArrowLeft, Trash2, CreditCard } from 'lucide-vue-next'

import UiCard from '../components/UiCard.vue'
import UiButton from '../components/UiButton.vue'
import UiBadge from '../components/UiBadge.vue'
import UiInput from '../components/UiInput.vue'
import UiSelect from '../components/UiSelect.vue'
import UiTable from '../components/UiTable.vue'
import UiSpinner from '../components/UiSpinner.vue'
import Avatar from '../components/Avatar.vue'
import PhoneCell from '../components/PhoneCell.vue'
import { useToast } from '../composables/useToast.js'
import { useConfirm } from '../composables/useConfirm.js'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const confirm = useConfirm()

const data = ref(null)
const plans = ref([])
const editCredits = ref(0)
const editJokes = ref(0)
const editPlan = ref('')
const editAdmin = ref(false)
const deleting = ref(false)

const callColumns = [
  { key: 'phone_number',          label: 'Phone',  mono: true,  width: '160px' },
  { key: 'custom_joke_prompt',    label: 'Scenario' },
  { key: 'status',                label: 'Status', width: '140px' },
  { key: 'call_duration_seconds', label: 'Dur',    align: 'right', width: '70px' },
  { key: 'created_at',            label: 'Date',   width: '130px' },
]

onMounted(async () => {
  try {
    const [u, p] = await Promise.all([
      axios.get(`/admin-api/users/${route.params.id}`),
      axios.get('/admin-api/plans'),
    ])
    data.value = u.data
    plans.value = p.data
    editCredits.value = u.data.credits
    editJokes.value = u.data.jokes ?? 0
    editPlan.value = u.data.user.subscription_plan || ''
    editAdmin.value = u.data.user.is_admin
  } catch (e) { toast.error(e) }
})

async function setCredits() {
  try {
    await axios.put(`/admin-api/users/${route.params.id}`, { credits: editCredits.value })
    data.value.credits = editCredits.value
    toast.success('Credits actualizados')
  } catch (e) { toast.error(e) }
}
async function setJokes() {
  try {
    await axios.put(`/admin-api/users/${route.params.id}`, { jokes: editJokes.value })
    data.value.jokes = editJokes.value
    toast.success('Chistes actualizados')
  } catch (e) { toast.error(e) }
}
async function setPlan() {
  try {
    await axios.put(`/admin-api/users/${route.params.id}`, { subscription_plan: editPlan.value || null })
    data.value.user.subscription_plan = editPlan.value
    toast.success('Plan actualizado')
  } catch (e) { toast.error(e) }
}
async function setAdmin() {
  try {
    await axios.put(`/admin-api/users/${route.params.id}`, { is_admin: editAdmin.value })
    toast.success(editAdmin.value ? 'Admin habilitado' : 'Admin removido')
  } catch (e) { toast.error(e) }
}
async function deleteUser() {
  const name = data.value?.user?.name || data.value?.user?.email || 'este usuario'
  if (!await confirm({
    title: '¿Eliminar usuario?',
    body: `Vas a borrar COMPLETAMENTE a ${name} (créditos, llamadas, referidos). No se puede deshacer.`,
    danger: true,
    confirmLabel: 'Eliminar',
  })) return
  deleting.value = true
  try {
    await axios.delete(`/admin-api/users/${route.params.id}`)
    toast.success('Usuario eliminado')
    router.push('/admin/users')
  } catch (e) { toast.error(e) } finally { deleting.value = false }
}

function formatDate(d) { return d ? new Date(d).toLocaleDateString('es-MX') : '' }
function formatDateTime(d) { return d ? new Date(d).toLocaleString('es-MX', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }) : '' }
function formatDuration(s) { return s ? `${String(Math.floor(s/60)).padStart(2,'0')}:${String(s%60).padStart(2,'0')}` : '—' }
</script>
