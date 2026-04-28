<template>
  <component
    :is="tag"
    :class="[
      'relative surface-card surface-card-edge',
      hover && 'lift hover:border-white/15 hover:shadow-[var(--shadow-glow-md)]',
      $attrs.class,
    ]"
  >
    <header v-if="$slots.header || title" class="flex items-center justify-between px-5 pt-4 pb-3 border-b border-white/5">
      <div>
        <h2 v-if="title" class="text-[15px] font-semibold tracking-tight text-white">{{ title }}</h2>
        <p v-if="subtitle" class="text-xs text-gray-400 mt-0.5">{{ subtitle }}</p>
        <slot name="header" />
      </div>
      <div v-if="$slots.actions" class="flex items-center gap-2">
        <slot name="actions" />
      </div>
    </header>
    <div :class="['relative z-10', padded ? 'p-5' : '']">
      <slot />
    </div>
    <footer v-if="$slots.footer" class="px-5 py-3 border-t border-white/5 text-sm text-gray-400">
      <slot name="footer" />
    </footer>
  </component>
</template>

<script setup>
defineOptions({ inheritAttrs: false })
defineProps({
  tag:      { type: String, default: 'div' },
  title:    { type: String, default: '' },
  subtitle: { type: String, default: '' },
  hover:    { type: Boolean, default: false },
  padded:   { type: Boolean, default: true },
})
</script>
