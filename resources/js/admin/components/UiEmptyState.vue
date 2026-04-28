<template>
  <div class="text-center py-16 px-6 animate-[fade-in-up_0.4s_ease-out]">
    <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-white/5 border border-white/10 text-gray-400 mb-4">
      <component :is="icon" v-if="icon" class="w-6 h-6" />
      <span v-else class="text-2xl">{{ emoji || '📭' }}</span>
    </div>
    <h3 class="text-lg font-semibold text-white">{{ title }}</h3>
    <p v-if="body" class="text-sm text-gray-400 mt-2 max-w-md mx-auto">{{ body }}</p>
    <div v-if="$slots.action" class="mt-5 inline-flex">
      <slot name="action" />
    </div>
  </div>
</template>

<script setup>
defineProps({
  icon:  { type: [Object, Function], default: null },
  emoji: { type: String, default: '' },
  title: { type: String, required: true },
  body:  { type: String, default: '' },
})
</script>
