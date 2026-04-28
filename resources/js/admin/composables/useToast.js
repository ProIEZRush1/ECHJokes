// Thin wrapper over vue-sonner so pages don't import the lib directly.
// Usage: const toast = useToast(); toast.success('Plan creado'); toast.error(err)
import { toast as sonnerToast } from 'vue-sonner'

export function useToast() {
  return {
    success: (msg, opts) => sonnerToast.success(msg, opts),
    error:   (msg, opts) => sonnerToast.error(typeof msg === 'string' ? msg : (msg?.response?.data?.message || msg?.message || 'Algo salió mal'), opts),
    info:    (msg, opts) => sonnerToast.info(msg, opts),
    warning: (msg, opts) => sonnerToast.warning(msg, opts),
    message: (msg, opts) => sonnerToast(msg, opts),
    promise: (p, opts) => sonnerToast.promise(p, opts),
    dismiss: (id) => sonnerToast.dismiss(id),
  }
}
