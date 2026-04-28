<template>
  <div class="min-h-screen bg-app-glass text-white font-sans">
    <!-- Mobile header -->
    <div class="lg:hidden fixed top-0 left-0 right-0 h-14 bg-matrix-900/80 backdrop-blur-md border-b border-white/8 flex items-center justify-between px-4 z-40">
      <div class="flex items-center gap-2">
        <span class="w-8 h-8 grid place-items-center rounded-lg bg-gradient-to-br from-neon to-emerald-600 text-matrix-900 font-bold text-sm shadow-[0_0_18px_rgba(57,255,20,0.45)]">V</span>
        <h1 class="text-base font-bold tracking-tight">Vacilada</h1>
      </div>
      <button @click="mobileOpen = !mobileOpen" class="p-2 text-gray-300 hover:text-white transition" aria-label="Menu">
        <Menu v-if="!mobileOpen" class="w-6 h-6" />
        <X v-else class="w-6 h-6" />
      </button>
    </div>

    <!-- Mobile overlay -->
    <Transition enter-active-class="transition" enter-from-class="opacity-0" enter-to-class="opacity-100" leave-active-class="transition" leave-from-class="opacity-100" leave-to-class="opacity-0">
      <div v-if="mobileOpen" class="lg:hidden fixed inset-0 bg-black/60 z-30" @click="mobileOpen = false" />
    </Transition>

    <!-- Sidebar -->
    <aside
      :class="[
        'fixed left-0 top-0 h-screen bg-matrix-900/85 backdrop-blur-xl border-r border-white/8 flex flex-col z-40 transition-[transform,width] duration-300 ease-out',
        collapsed ? 'lg:w-[72px]' : 'lg:w-60',
        'w-64 lg:translate-x-0',
        mobileOpen ? 'translate-x-0' : '-translate-x-full',
      ]"
    >
      <!-- Brand -->
      <div class="px-4 py-4 border-b border-white/8 flex items-center gap-3">
        <span class="w-9 h-9 grid place-items-center rounded-xl bg-gradient-to-br from-neon to-emerald-600 text-matrix-900 font-extrabold shadow-[0_0_22px_rgba(57,255,20,0.45)] flex-shrink-0">V</span>
        <div v-if="!collapsed" class="min-w-0">
          <div class="text-[15px] font-semibold tracking-tight truncate">Vacilada</div>
          <div class="text-[11px] text-gray-500">Admin Console</div>
        </div>
      </div>

      <!-- Nav -->
      <nav class="flex-1 px-2 py-3 overflow-y-auto">
        <div v-for="(group, gi) in navGroups" :key="gi" class="mb-2">
          <div v-if="!collapsed" class="px-3 pt-3 pb-1.5 text-[10.5px] uppercase tracking-wider text-gray-600 font-semibold">
            {{ group.label }}
          </div>
          <router-link
            v-for="item in group.items"
            :key="item.to"
            :to="item.to"
            @click="mobileOpen = false"
            :class="[
              'group relative flex items-center gap-3 mx-1 my-0.5 px-3 py-2 rounded-lg text-[13.5px] font-medium transition-all border border-transparent',
              isActive(item.to)
                ? 'text-white bg-gradient-to-r from-neon/10 to-neon/0 border-neon/25 shadow-[inset_0_0_0_1px_rgba(57,255,20,0.15),0_6px_24px_-12px_rgba(57,255,20,0.45)]'
                : 'text-gray-400 hover:text-white hover:bg-white/5',
              collapsed && 'justify-center'
            ]"
          >
            <component
              :is="item.icon"
              class="w-[18px] h-[18px] flex-shrink-0"
              :class="isActive(item.to) ? 'text-neon' : 'text-gray-400 group-hover:text-white'"
            />
            <span v-if="!collapsed" class="truncate">{{ item.label }}</span>
            <span v-if="!collapsed && item.shortcut" class="ml-auto text-[10.5px] text-gray-600 font-mono">{{ item.shortcut }}</span>
          </router-link>
        </div>
      </nav>

      <!-- User -->
      <div class="border-t border-white/8 p-3">
        <button
          @click="userMenuOpen = !userMenuOpen"
          :class="['w-full flex items-center gap-3 px-2 py-2 rounded-lg hover:bg-white/5 transition', collapsed && 'justify-center']"
        >
          <Avatar :name="user?.name || '?'" size="sm" />
          <div v-if="!collapsed" class="flex-1 text-left min-w-0">
            <div class="text-[13px] font-semibold truncate">{{ user?.name || '—' }}</div>
            <div class="text-[11px] text-gray-500 truncate">{{ user?.email }}</div>
          </div>
          <ChevronDown v-if="!collapsed" class="w-4 h-4 text-gray-500" />
        </button>

        <Transition enter-active-class="transition duration-150" enter-from-class="opacity-0 translate-y-1" enter-to-class="opacity-100 translate-y-0" leave-active-class="transition duration-100" leave-from-class="opacity-100" leave-to-class="opacity-0">
          <div v-if="userMenuOpen && !collapsed" class="mt-2 surface-card p-1 text-[13px]">
            <button class="w-full flex items-center gap-2 px-3 py-2 rounded-md text-gray-300 hover:text-white hover:bg-white/5 transition" @click="logout">
              <LogOut class="w-4 h-4" /> Cerrar sesión
            </button>
          </div>
        </Transition>

        <button
          @click="collapsed = !collapsed"
          class="hidden lg:flex items-center justify-center w-full mt-2 py-1.5 rounded-md text-gray-500 hover:text-white hover:bg-white/5 transition"
          :title="collapsed ? 'Expandir' : 'Colapsar'"
        >
          <PanelLeftOpen v-if="collapsed" class="w-4 h-4" />
          <PanelLeftClose v-else class="w-4 h-4" />
        </button>
      </div>
    </aside>

    <!-- Main content -->
    <main
      :class="[
        'min-h-screen pt-14 lg:pt-0 transition-[margin] duration-300',
        collapsed ? 'lg:ml-[72px]' : 'lg:ml-60',
      ]"
    >
      <router-view v-slot="{ Component, route }">
        <Transition mode="out-in" enter-active-class="transition duration-200" enter-from-class="opacity-0 translate-y-1" enter-to-class="opacity-100 translate-y-0" leave-active-class="transition duration-100" leave-from-class="opacity-100" leave-to-class="opacity-0">
          <component :is="Component" :key="route.fullPath" />
        </Transition>
      </router-view>
    </main>

    <!-- Toast container -->
    <Toaster
      position="top-right"
      :toast-options="{
        style: {
          background: 'rgba(20,20,20,0.92)',
          backdropFilter: 'blur(14px)',
          border: '1px solid rgba(255,255,255,0.08)',
          color: '#f5f5f5',
          fontSize: '13.5px',
        }
      }"
      theme="dark"
      rich-colors
      close-button
    />

    <!-- Global confirm dialog -->
    <UiConfirm />
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useStorage } from '@vueuse/core'
import { Toaster } from 'vue-sonner'
import axios from 'axios'

import {
  LayoutDashboard, Phone, Rocket, Users, CreditCard,
  Theater, BarChart3, Gift, Palette,
  Menu, X, ChevronDown, LogOut,
  PanelLeftOpen, PanelLeftClose,
} from 'lucide-vue-next'

import Avatar from './components/Avatar.vue'
import UiConfirm from './components/UiConfirm.vue'

const router = useRouter()
const route  = useRoute()
const user = ref(null)
const mobileOpen = ref(false)
const userMenuOpen = ref(false)
const collapsed = useStorage('admin-sidebar-collapsed', false)

const navGroups = [
  {
    label: 'Operación',
    items: [
      { to: '/admin',           icon: LayoutDashboard, label: 'Dashboard',  shortcut: '⌘1' },
      { to: '/admin/calls',     icon: Phone,           label: 'Llamadas',   shortcut: '⌘2' },
      { to: '/admin/launch',    icon: Rocket,          label: 'Launch Call' },
    ],
  },
  {
    label: 'Datos',
    items: [
      { to: '/admin/users',    icon: Users,    label: 'Users' },
      { to: '/admin/plans',    icon: CreditCard, label: 'Plans' },
      { to: '/admin/presets',  icon: Theater,  label: 'Presets' },
    ],
  },
  {
    label: 'Sistema',
    items: [
      { to: '/admin/billing',   icon: BarChart3, label: 'Billing' },
      { to: '/admin/referrals', icon: Gift,      label: 'Referrals' },
      { to: '/admin/brand',     icon: Palette,   label: 'Brand & Press' },
    ],
  },
]

function isActive(path) {
  if (path === '/admin') return route.path === '/admin'
  return route.path === path || route.path.startsWith(path + '/')
}

onMounted(async () => {
  try {
    const { data } = await axios.get('/admin-api/me')
    user.value = data.user
  } catch {
    router.push('/admin/login')
  }
})

async function logout() {
  await axios.post('/admin-api/logout')
  router.push('/admin/login')
}
</script>
