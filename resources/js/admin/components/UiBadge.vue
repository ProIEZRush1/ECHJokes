<template>
  <span
    class="inline-flex items-center gap-1.5 rounded-full text-[11.5px] font-semibold px-2.5 py-0.5 border"
    :style="style"
  >
    <span
      v-if="dot"
      class="inline-block w-1.5 h-1.5 rounded-full"
      :style="{ background: 'currentColor', boxShadow: pulse ? '0 0 0 0 currentColor' : undefined }"
      :class="pulse && 'animate-[pulse-dot_var(--tw-anim,1.6s)_ease-out_infinite]'"
    />
    <slot>{{ resolved.label }}</slot>
  </span>
</template>

<script setup>
import { computed } from 'vue'
import { statusOf } from '../lib/statusColors.js'

const props = defineProps({
  status:  { type: String, default: '' },
  color:   { type: String, default: '' },          // override color (hex or var)
  dot:     { type: Boolean, default: true },
  pulse:   { type: Boolean, default: false },
})

const resolved = computed(() => statusOf(props.status))
const style = computed(() => {
  const c = props.color || resolved.value.fg
  return {
    color: c,
    borderColor: 'currentColor',
    background: `color-mix(in srgb, ${c} 12%, transparent)`,
  }
})
</script>
