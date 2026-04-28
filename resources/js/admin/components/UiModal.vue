<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="modelValue"
        class="fixed inset-0 z-[80] bg-black/60 backdrop-blur-sm flex items-center justify-center p-4"
        @click.self="onBackdrop"
      >
        <Transition
          enter-active-class="transition duration-200 cubic-bezier(0.22,1,0.36,1)"
          enter-from-class="opacity-0 scale-95 translate-y-2"
          enter-to-class="opacity-100 scale-100 translate-y-0"
          leave-active-class="transition duration-150 ease-in"
          leave-from-class="opacity-100 scale-100"
          leave-to-class="opacity-0 scale-95"
          appear
        >
          <div
            v-if="modelValue"
            ref="dialog"
            role="dialog"
            aria-modal="true"
            :class="[
              'relative w-full surface-card surface-card-edge shadow-[var(--shadow-lift)] max-h-[90vh] overflow-y-auto',
              widthClass,
            ]"
          >
            <header v-if="title || $slots.header" class="flex items-start justify-between gap-3 px-6 pt-5 pb-4 border-b border-white/5">
              <div>
                <h2 v-if="title" class="text-lg font-semibold text-white">{{ title }}</h2>
                <p v-if="subtitle" class="text-sm text-gray-400 mt-1">{{ subtitle }}</p>
                <slot name="header" />
              </div>
              <button
                type="button"
                @click="close"
                class="text-gray-500 hover:text-white transition p-1 -m-1 rounded-md focus:outline-none focus-visible:ring-2 focus-visible:ring-neon/50"
                aria-label="Cerrar"
              >
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M18 6 6 18M6 6l12 12" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              </button>
            </header>

            <div class="p-6">
              <slot />
            </div>

            <footer v-if="$slots.footer" class="px-6 py-4 border-t border-white/5 flex items-center justify-end gap-2">
              <slot name="footer" />
            </footer>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useEventListener } from '@vueuse/core'

const props = defineProps({
  modelValue:    { type: Boolean, required: true },
  title:         { type: String, default: '' },
  subtitle:      { type: String, default: '' },
  size:          { type: String, default: 'md' },     // sm | md | lg | xl
  closeOnBackdrop: { type: Boolean, default: true },
})
const emit = defineEmits(['update:modelValue', 'close'])

const widthClass = computed(() => ({
  sm: 'max-w-md rounded-2xl',
  md: 'max-w-lg rounded-2xl',
  lg: 'max-w-2xl rounded-2xl',
  xl: 'max-w-4xl rounded-2xl',
}[props.size]))

function close() {
  emit('update:modelValue', false)
  emit('close')
}
function onBackdrop() {
  if (props.closeOnBackdrop) close()
}

useEventListener(window, 'keydown', (e) => {
  if (props.modelValue && e.key === 'Escape') close()
})

// Lock body scroll while open
watch(() => props.modelValue, (open) => {
  if (typeof document === 'undefined') return
  document.body.style.overflow = open ? 'hidden' : ''
})
onBeforeUnmount(() => {
  if (typeof document !== 'undefined') document.body.style.overflow = ''
})
</script>
