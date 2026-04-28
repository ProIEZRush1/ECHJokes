<template>
  <span class="inline-flex items-center gap-1.5 font-mono text-[13px]">
    <span>{{ display }}</span>
    <button
      v-if="phone"
      type="button"
      @click.stop="copy"
      class="text-gray-500 hover:text-neon transition opacity-0 group-hover:opacity-100"
      title="Copiar"
    >
      <svg v-if="!copied" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
      </svg>
      <svg v-else class="w-3.5 h-3.5 text-neon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
        <path d="M20 6 9 17l-5-5" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
    </button>
  </span>
</template>

<script setup>
import { computed, ref } from 'vue'

const props = defineProps({
  phone:    { type: String, default: '' },
  mask:     { type: Boolean, default: true },
})

const copied = ref(false)

const display = computed(() => {
  if (!props.phone) return '—'
  if (!props.mask) return props.phone
  if (props.phone.length < 6) return props.phone
  return props.phone.slice(0, 6) + '****' + props.phone.slice(-2)
})

async function copy() {
  if (!props.phone || !navigator.clipboard) return
  try {
    await navigator.clipboard.writeText(props.phone)
    copied.value = true
    setTimeout(() => { copied.value = false }, 1500)
  } catch {}
}
</script>
