<template>
  <div
    :class="[
      'ad-banner w-full flex justify-center my-5',
      props.format === 'leaderboard' && 'hidden md:flex',
    ]"
  >
    <div class="w-full max-w-[728px] overflow-hidden">
      <ins
        class="adsbygoogle"
        style="display:block"
        :data-ad-client="pubId"
        :data-ad-slot="props.slot"
        data-ad-format="auto"
        data-full-width-responsive="true"
      />
    </div>
  </div>
</template>

<script setup>
import { onMounted } from 'vue'
import { ADSENSE_PUB_ID } from '../adsense.js'

const props = defineProps({
  slot:   { type: String, required: true },
  format: { type: String, default: 'auto' },
})

const pubId = ADSENSE_PUB_ID

onMounted(() => {
  try {
    ;(window.adsbygoogle = window.adsbygoogle || []).push({})
  } catch {}
})
</script>
