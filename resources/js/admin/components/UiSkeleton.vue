<template>
  <span
    class="skeleton block"
    :style="{ width: w, height: h, borderRadius: rounded }"
    aria-hidden="true"
  />
</template>

<script setup>
defineProps({
  w:       { type: String, default: '100%' },
  h:       { type: String, default: '14px' },
  rounded: { type: String, default: '8px' },
})
</script>
