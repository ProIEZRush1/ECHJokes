<template>
  <label class="block">
    <span v-if="label" class="block text-[11.5px] uppercase tracking-wide text-gray-400 font-semibold mb-1.5">{{ label }}</span>
    <span class="relative block">
      <span v-if="$slots.prefix" class="absolute inset-y-0 left-3 flex items-center text-gray-500 pointer-events-none">
        <slot name="prefix" />
      </span>
      <input
        :value="modelValue"
        @input="$emit('update:modelValue', $event.target.value)"
        :type="type"
        :placeholder="placeholder"
        :required="required"
        :disabled="disabled"
        :class="[
          'w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder:text-gray-500',
          'focus:outline-none focus:border-neon/50 focus:ring-2 focus:ring-neon/20 transition',
          $slots.prefix && 'pl-9',
          $slots.suffix && 'pr-9',
          disabled && 'opacity-60 cursor-not-allowed',
          error && 'border-red-500/50 focus:border-red-500 focus:ring-red-500/20',
        ]"
      />
      <span v-if="$slots.suffix" class="absolute inset-y-0 right-3 flex items-center text-gray-500">
        <slot name="suffix" />
      </span>
    </span>
    <span v-if="error" class="block text-xs text-red-400 mt-1">{{ error }}</span>
    <span v-else-if="hint" class="block text-xs text-gray-500 mt-1">{{ hint }}</span>
  </label>
</template>

<script setup>
defineProps({
  modelValue:  { type: [String, Number], default: '' },
  label:       { type: String, default: '' },
  placeholder: { type: String, default: '' },
  type:        { type: String, default: 'text' },
  required:    { type: Boolean, default: false },
  disabled:    { type: Boolean, default: false },
  hint:        { type: String, default: '' },
  error:       { type: String, default: '' },
})
defineEmits(['update:modelValue'])
</script>
