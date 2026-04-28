<template>
  <component
    :is="tag"
    :class="[
      'inline-flex items-center justify-center gap-2 font-semibold rounded-lg transition-all border whitespace-nowrap select-none',
      'focus:outline-none focus-visible:ring-2 focus-visible:ring-neon/50 focus-visible:ring-offset-2 focus-visible:ring-offset-matrix-900',
      sizeClasses,
      variantClasses,
      (loading || disabled) && 'opacity-60 cursor-not-allowed',
    ]"
    :type="tag === 'button' ? type : undefined"
    :disabled="(loading || disabled) && tag === 'button'"
    @click="onClick"
  >
    <UiSpinner v-if="loading" :size="size === 'sm' ? 12 : size === 'lg' ? 16 : 14" />
    <slot name="icon-left" v-else />
    <slot />
    <slot name="icon-right" />
  </component>
</template>

<script setup>
import { computed } from 'vue'
import UiSpinner from './UiSpinner.vue'

const props = defineProps({
  tag:      { type: String, default: 'button' },
  type:     { type: String, default: 'button' },
  variant:  { type: String, default: 'primary' }, // primary | secondary | danger | ghost | neonGhost
  size:     { type: String, default: 'md' },      // sm | md | lg
  loading:  { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
})
const emit = defineEmits(['click'])

function onClick(e) {
  if (props.loading || props.disabled) return
  emit('click', e)
}

const sizeClasses = computed(() => ({
  sm: 'text-xs px-3 py-1.5 rounded-md',
  md: 'text-[13px] px-4 py-2',
  lg: 'text-sm px-5 py-2.5',
}[props.size]))

const variantClasses = computed(() => ({
  primary:    'bg-neon text-matrix-900 border-neon hover:brightness-110 hover:shadow-[0_8px_24px_-12px_rgba(57,255,20,0.6)]',
  secondary:  'bg-white/5 text-white border-white/10 hover:bg-white/10 hover:border-white/20',
  ghost:      'bg-transparent text-gray-300 border-transparent hover:bg-white/5 hover:text-white',
  neonGhost:  'bg-transparent text-neon border-neon/30 hover:bg-neon/10 hover:border-neon/60',
  danger:     'bg-red-500/15 text-red-300 border-red-500/30 hover:bg-red-500/25 hover:text-red-200',
}[props.variant]))
</script>
