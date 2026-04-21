<template>
  <a :href="whatsappUrl" target="_blank" rel="noopener" @click="trackClick"
     class="flex items-center justify-center gap-2 bg-[#25D366] hover:bg-[#1ebe5a] text-white rounded-xl py-3 px-4 font-semibold text-sm transition">
    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
      <path d="M13.601 2.326A7.854 7.854 0 0 0 7.994 0C3.627 0 .068 3.558.064 7.926c0 1.399.366 2.76 1.057 3.965L0 16l4.204-1.102a7.9 7.9 0 0 0 3.79.965h.004c4.368 0 7.926-3.558 7.93-7.93A7.9 7.9 0 0 0 13.6 2.326zM7.994 14.521a6.6 6.6 0 0 1-3.356-.92l-.24-.144-2.494.654.666-2.433-.156-.251a6.56 6.56 0 0 1-1.007-3.505c0-3.626 2.957-6.584 6.591-6.584a6.56 6.56 0 0 1 4.66 1.931 6.56 6.56 0 0 1 1.928 4.66c-.004 3.639-2.961 6.592-6.592 6.592"/>
    </svg>
    <slot>Compartir por WhatsApp</slot>
  </a>
</template>

<script setup>
import { computed, onMounted } from 'vue'
import axios from 'axios'

const props = defineProps({
  url: { type: String, required: true },
  victimName: { type: String, default: '' },
  callId: { type: String, default: '' },
})

const VARIANTS = [
  { id: 'A', msg: (v, u) => `😂 Mira la vacilada que le hice a ${v || 'un compa'}, no puedo 💀\n\n🎧 ${u}` },
  { id: 'B', msg: (v, u) => `Hice que una IA llamara a ${v || 'mi compa'} jajaja escucha esto → ${u}` },
  { id: 'C', msg: (v, u) => `Esto lo tengo que compartir, no puedo respirar 😭😂\n\n${u}` },
]

function pickVariant() {
  const stored = localStorage.getItem('vacilada_wa_variant')
  if (stored && VARIANTS.find(v => v.id === stored)) return stored
  const v = VARIANTS[Math.floor(Math.random() * VARIANTS.length)].id
  localStorage.setItem('vacilada_wa_variant', v)
  return v
}

const variant = pickVariant()
const variantObj = computed(() => VARIANTS.find(v => v.id === variant))
const caption = computed(() => variantObj.value.msg(props.victimName, urlWithUtms.value))

const urlWithUtms = computed(() => {
  const u = new URL(props.url, window.location.origin)
  u.searchParams.set('utm_source', 'whatsapp')
  u.searchParams.set('utm_medium', 'share_button')
  u.searchParams.set('utm_campaign', props.callId || 'share')
  u.searchParams.set('utm_content', `var_${variant}`)
  return u.toString()
})

const whatsappUrl = computed(() => `https://wa.me/?text=${encodeURIComponent(caption.value)}`)

onMounted(() => {
  logEvent('impression')
})

function trackClick() {
  logEvent('click')
}

function logEvent(eventType) {
  axios.post('/api/ab/event', {
    test_name: 'whatsapp_share_caption',
    variant,
    event_type: eventType,
    call_id: props.callId,
    metadata: { victim_name: props.victimName, device: /mobile/i.test(navigator.userAgent) ? 'mobile' : 'desktop' },
  }).catch(() => {})
}
</script>
