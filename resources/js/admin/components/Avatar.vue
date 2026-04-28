<template>
  <span
    :class="[
      'inline-flex items-center justify-center font-semibold text-white select-none',
      sizeClass, roundedClass,
    ]"
    :style="{ background: bg, color: fg, borderColor: 'rgba(255,255,255,.06)', borderWidth: '1px', borderStyle: 'solid' }"
    :title="name"
  >
    {{ initial }}
  </span>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  name:    { type: String, default: '?' },
  size:    { type: String, default: 'md' }, // xs | sm | md | lg
  square:  { type: Boolean, default: false },
})

// Deterministic color from name
const PALETTE = [
  ['#39FF14', '#062a02'],
  ['#7ec3ff', '#062f4a'],
  ['#c89bff', '#2b0a52'],
  ['#ffc14d', '#3d2a05'],
  ['#ff8aa3', '#460c1a'],
  ['#7afad9', '#063a2e'],
]

function hash(s) {
  let h = 0
  for (let i = 0; i < s.length; i++) h = (h << 5) - h + s.charCodeAt(i) | 0
  return Math.abs(h)
}
const idx = computed(() => hash(props.name || '?') % PALETTE.length)
const bg = computed(() => PALETTE[idx.value][0])
const fg = computed(() => PALETTE[idx.value][1])
const initial = computed(() => (props.name || '?').trim().charAt(0).toUpperCase() || '?')

const sizeClass = computed(() => ({
  xs: 'w-6 h-6 text-[10px]',
  sm: 'w-7 h-7 text-xs',
  md: 'w-9 h-9 text-sm',
  lg: 'w-11 h-11 text-base',
}[props.size]))
const roundedClass = computed(() => props.square ? 'rounded-lg' : 'rounded-full')
</script>
