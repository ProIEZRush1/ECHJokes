// Promise-based confirmation dialog. Mounts a single global instance.
// Usage:
//   const confirm = useConfirm()
//   if (await confirm({ title: '¿Borrar usuario?', body: 'No se puede deshacer.', danger: true })) { ... }
import { ref } from 'vue'

const state = ref({
  open: false,
  title: '',
  body: '',
  confirmLabel: 'Confirmar',
  cancelLabel: 'Cancelar',
  danger: false,
  resolve: null,
})

export function useConfirm() {
  return (opts = {}) => new Promise((resolve) => {
    state.value = {
      open: true,
      title: opts.title || '¿Estás seguro?',
      body: opts.body || '',
      confirmLabel: opts.confirmLabel || (opts.danger ? 'Borrar' : 'Confirmar'),
      cancelLabel: opts.cancelLabel || 'Cancelar',
      danger: !!opts.danger,
      resolve,
    }
  })
}

export function _confirmState() { return state }

export function _resolveConfirm(answer) {
  const r = state.value.resolve
  state.value = { ...state.value, open: false, resolve: null }
  if (r) r(answer)
}
