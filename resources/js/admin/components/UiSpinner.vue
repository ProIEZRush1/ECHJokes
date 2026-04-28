<template>
  <span
    class="inline-block align-middle rounded-full border-current border-t-transparent animate-spin"
    :style="{ width: size + 'px', height: size + 'px', borderWidth: thickness + 'px' }"
    role="status"
    aria-label="Cargando"
  />
</template>

<script setup>
defineProps({
  size:      { type: Number, default: 16 },
  thickness: { type: Number, default: 2 },
})
</script>
