<template>
  <UiModal :model-value="state.open" :title="state.title" size="sm" @update:model-value="cancel">
    <p class="text-sm text-gray-300">{{ state.body }}</p>

    <template #footer>
      <UiButton variant="ghost" size="sm" @click="cancel">{{ state.cancelLabel }}</UiButton>
      <UiButton :variant="state.danger ? 'danger' : 'primary'" size="sm" @click="confirm">{{ state.confirmLabel }}</UiButton>
    </template>
  </UiModal>
</template>

<script setup>
import UiModal from './UiModal.vue'
import UiButton from './UiButton.vue'
import { _confirmState, _resolveConfirm } from '../composables/useConfirm.js'

const state = _confirmState()
function confirm() { _resolveConfirm(true) }
function cancel()  { _resolveConfirm(false) }
</script>
