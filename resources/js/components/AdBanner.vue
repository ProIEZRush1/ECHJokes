<template>
  <div
    v-if="enabled"
    :class="[
      'ad-banner w-full flex justify-center my-5',
      props.format === 'leaderboard' && 'hidden md:flex',
    ]"
  >
    <div class="w-full max-w-[728px] border border-white/5 rounded-xl bg-white/[0.02] overflow-hidden">
      <ins
        class="adsbygoogle"
        :style="adStyle"
        :data-ad-client="pubId"
        :data-ad-slot="props.slot"
        :data-ad-format="props.responsive ? 'auto' : undefined"
        :data-full-width-responsive="props.responsive ? 'true' : undefined"
      />
      <p class="text-[9px] text-gray-600 text-center py-0.5">Publicidad</p>
    </div>
  </div>
</template>

<script setup>
import { onMounted, computed } from 'vue'
import { ADSENSE_PUB_ID } from '../adsense.js'

const props = defineProps({
  slot:       { type: String, required: true },
  format:     { type: String, default: 'auto' },
  responsive: { type: Boolean, default: true },
})

const pubId = ADSENSE_PUB_ID
const enabled = computed(() => !!pubId && !pubId.includes('XXXX'))

const adStyle = computed(() => {
  if (props.format === 'leaderboard') return 'display:inline-block;width:728px;height:90px'
  if (props.format === 'rectangle')   return 'display:inline-block;width:336px;height:280px'
  return 'display:block'
})

onMounted(() => {
  if (!enabled.value) return
  try {
    ;(window.adsbygoogle = window.adsbygoogle || []).push({})
  } catch {}
})
</script>
