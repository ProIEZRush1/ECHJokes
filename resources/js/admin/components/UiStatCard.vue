<template>
  <UiCard hover :padded="false" class="overflow-hidden animate-[fade-in-up_0.45s_ease-out_both]">
    <div class="p-5">
      <div class="flex items-center gap-2 text-gray-400 text-[12.5px] font-medium">
        <component :is="icon" v-if="icon" class="w-4 h-4" />
        <span>{{ label }}</span>
      </div>

      <div class="flex items-baseline gap-2 mt-2">
        <div
          class="font-mono font-bold text-3xl tracking-tight"
          :class="hot && 'text-neon drop-shadow-[0_0_18px_rgba(57,255,20,0.55)]'"
        >{{ formatted }}</div>
        <span
          v-if="delta !== null && delta !== undefined"
          class="text-xs font-semibold"
          :class="(delta >= 0) ? 'text-neon' : 'text-red-400'"
        >
          {{ delta >= 0 ? '▲' : '▼' }} {{ Math.abs(delta) }}{{ deltaSuffix }}
        </span>
        <span
          v-if="liveDot"
          class="inline-block w-2 h-2 rounded-full bg-neon ml-1"
          style="box-shadow: 0 0 0 0 rgba(57,255,20,0.55)"
          :class="'animate-[pulse-dot_1.6s_ease-out_infinite]'"
        />
      </div>

      <div v-if="hint" class="text-xs text-gray-500 mt-1.5">{{ hint }}</div>
    </div>

    <!-- Optional sparkline at bottom -->
    <svg
      v-if="spark && spark.length > 1"
      class="block w-full"
      style="height:38px;color:var(--color-neon)"
      :viewBox="`0 0 ${spark.length - 1} 100`"
      preserveAspectRatio="none"
    >
      <defs>
        <linearGradient :id="`sparkFade-${uid}`" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%"   stop-color="currentColor" stop-opacity="0.45" />
          <stop offset="100%" stop-color="currentColor" stop-opacity="0" />
        </linearGradient>
      </defs>
      <path :d="sparkArea" :fill="`url(#sparkFade-${uid})`" stroke="none" />
      <path :d="sparkLine" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" />
    </svg>
  </UiCard>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import UiCard from './UiCard.vue'

const props = defineProps({
  label:     { type: String, required: true },
  value:     { type: [Number, String], required: true },
  delta:     { type: Number, default: null },
  deltaSuffix: { type: String, default: '%' },
  hint:      { type: String, default: '' },
  icon:      { type: [Object, Function], default: null },
  hot:       { type: Boolean, default: false },
  liveDot:   { type: Boolean, default: false },
  spark:     { type: Array, default: null },
  animate:   { type: Boolean, default: true },
})

const uid = Math.random().toString(36).slice(2, 9)
const display = ref(0)

function isNum(v) { return typeof v === 'number' && Number.isFinite(v) }

function tween(target) {
  if (!isNum(target)) { display.value = target; return }
  if (!props.animate) { display.value = target; return }
  const start = performance.now()
  const from = isNum(display.value) ? display.value : 0
  const dur = 600
  function step(now) {
    const t = Math.min(1, (now - start) / dur)
    const eased = 1 - Math.pow(1 - t, 3)
    display.value = Math.round(from + (target - from) * eased)
    if (t < 1) requestAnimationFrame(step)
  }
  requestAnimationFrame(step)
}

onMounted(() => tween(props.value))
watch(() => props.value, (v) => tween(v))

const formatted = computed(() => isNum(display.value) ? display.value.toLocaleString('es-MX') : props.value)

// Sparkline path math
const sparkLine = computed(() => {
  if (!props.spark || props.spark.length < 2) return ''
  const max = Math.max(...props.spark) || 1
  const min = Math.min(...props.spark)
  const range = (max - min) || 1
  return props.spark.map((v, i) => {
    const x = i
    const y = 100 - ((v - min) / range) * 90 - 5
    return (i === 0 ? 'M' : 'L') + x + ' ' + y.toFixed(2)
  }).join(' ')
})
const sparkArea = computed(() => {
  if (!sparkLine.value) return ''
  return sparkLine.value + ` L${props.spark.length - 1} 100 L0 100 Z`
})
</script>
