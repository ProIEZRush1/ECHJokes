<template>
  <label class="block">
    <span v-if="label" class="block text-[11.5px] uppercase tracking-wide text-gray-400 font-semibold mb-1.5">{{ label }}</span>
    <span class="relative block">
      <select
        :value="modelValue"
        @change="$emit('update:modelValue', $event.target.value)"
        :required="required"
        :disabled="disabled"
        :class="[
          'appearance-none w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 pr-9 text-sm text-white',
          'focus:outline-none focus:border-neon/50 focus:ring-2 focus:ring-neon/20 transition',
          disabled && 'opacity-60 cursor-not-allowed',
        ]"
      >
        <option v-if="placeholder" :value="''" disabled>{{ placeholder }}</option>
        <option v-for="opt in normalizedOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
      </select>
      <svg class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-500 pointer-events-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="m6 9 6 6 6-6" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
    </span>
    <span v-if="hint" class="block text-xs text-gray-500 mt-1">{{ hint }}</span>
  </label>
</template>

<script setup>
import { computed } from 'vue'
const props = defineProps({
  modelValue:  { type: [String, Number], default: '' },
  label:       { type: String, default: '' },
  options:     { type: Array, default: () => [] }, // [{label,value}] or strings
  placeholder: { type: String, default: '' },
  required:    { type: Boolean, default: false },
  disabled:    { type: Boolean, default: false },
  hint:        { type: String, default: '' },
})
defineEmits(['update:modelValue'])

const normalizedOptions = computed(() => props.options.map((o) =>
  typeof o === 'string' ? { value: o, label: o } : o
))
</script>
