// src/composables/useToast.js
import { ref } from 'vue'

const toasts = ref([])

export function useToast() {
  function add(message, type = 'info', duration = 3500) {
    const id = Date.now()
    toasts.value.push({ id, message, type })
    setTimeout(() => { toasts.value = toasts.value.filter(t => t.id !== id) }, duration)
  }
  return {
    toasts,
    success: (msg) => add(msg, 'success'),
    error:   (msg) => add(msg, 'error'),
    info:    (msg) => add(msg, 'info'),
  }
}
