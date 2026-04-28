<template>
  <div class="p-6 lg:p-8 space-y-5 max-w-[1200px]">
    <header>
      <div class="text-[11.5px] uppercase tracking-wider text-gray-500 font-semibold mb-1">Sistema</div>
      <h1 class="text-[26px] font-bold tracking-tight">Brand & Press</h1>
      <p class="text-sm text-gray-400 mt-1.5">Todos los assets de marca y links útiles para marketing.</p>
    </header>

    <!-- Logo + Wordmark -->
    <div class="grid md:grid-cols-2 gap-4">
      <UiCard title="Logo">
        <div class="rounded-lg p-6 flex items-center justify-center mb-3" style="background:#0a0a0a">
          <img src="/brand/logo.png" alt="Vacilada logo" class="w-32 h-32" />
        </div>
        <UiButton variant="primary" tag="a" href="/brand/logo.png" download class="w-full">
          <Download class="w-4 h-4" /> Descargar PNG
        </UiButton>
      </UiCard>

      <UiCard title="Wordmark">
        <div class="rounded-lg p-6 flex items-center justify-center mb-3" style="background:#0a0a0a;min-height:134px">
          <img src="/brand/wordmark.png" alt="Vacilada wordmark" class="max-w-full h-auto" />
        </div>
        <UiButton variant="primary" tag="a" href="/brand/wordmark.png" download class="w-full">
          <Download class="w-4 h-4" /> Descargar PNG
        </UiButton>
      </UiCard>
    </div>

    <!-- OG image -->
    <UiCard title="OG image" subtitle="Link share / Twitter / FB">
      <div class="rounded-lg p-4 mb-3" style="background:#0a0a0a">
        <img src="/brand/og-image.png" alt="OG image" class="w-full rounded" />
      </div>
      <UiButton variant="primary" tag="a" href="/brand/og-image.png" download class="w-full">
        <Download class="w-4 h-4" /> Descargar PNG
      </UiButton>
    </UiCard>

    <!-- Banners -->
    <UiCard title="Banners">
      <div class="grid md:grid-cols-3 gap-3">
        <div v-for="b in banners" :key="b.file">
          <div class="rounded-lg p-3 mb-2 flex items-center justify-center" style="background:#0a0a0a;min-height:140px">
            <img :src="`/brand/${b.file}`" :alt="b.label" class="max-w-full max-h-32 rounded" />
          </div>
          <div class="text-[11px] text-gray-500 mb-1">{{ b.label }} · <span class="text-gray-400">{{ b.size }}</span></div>
          <UiButton variant="secondary" size="sm" tag="a" :href="`/brand/${b.file}`" download class="w-full">Descargar</UiButton>
        </div>
      </div>
    </UiCard>

    <!-- Social media kit -->
    <UiCard title="Social media kit" subtitle="Profile pic, FB cover, feed posts (Instagram / TikTok / Facebook)">
      <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
        <div v-for="s in social" :key="s.file">
          <div class="rounded-lg p-2 mb-2 flex items-center justify-center" style="background:#0a0a0a;min-height:160px">
            <img :src="`/brand/social/${s.file}`" :alt="s.label" class="max-w-full max-h-40 rounded" />
          </div>
          <div class="text-[11px] text-gray-300 mb-1 font-semibold">{{ s.label }}</div>
          <div class="text-[10px] text-gray-600 mb-1">{{ s.size }}</div>
          <UiButton variant="secondary" size="sm" tag="a" :href="`/brand/social/${s.file}`" download class="w-full">PNG</UiButton>
        </div>
      </div>
    </UiCard>

    <!-- Palette -->
    <UiCard title="Paleta" subtitle="Tipografía: JetBrains Mono (monospace) · Inter (UI)">
      <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="rounded-lg p-4 text-center" style="background:#39FF14;color:#0a0a0a;font-weight:700">
          <div>Neón</div><code class="text-xs">#39FF14</code>
        </div>
        <div class="rounded-lg p-4 text-center border border-white/10" style="background:#0a0a0a">
          <div class="text-white">Black</div><code class="text-xs text-gray-500">#0a0a0a</code>
        </div>
        <div class="rounded-lg p-4 text-center border border-white/10" style="background:#111111">
          <div class="text-white">Surface 800</div><code class="text-xs text-gray-500">#111111</code>
        </div>
        <div class="rounded-lg p-4 text-center border border-white/10" style="background:#1a1a1a">
          <div class="text-white">Surface 700</div><code class="text-xs text-gray-500">#1a1a1a</code>
        </div>
      </div>
    </UiCard>

    <!-- Social handles -->
    <UiCard title="Redes sociales">
      <div class="space-y-1 text-sm">
        <div class="flex justify-between py-2 border-b border-white/5">
          <span class="text-gray-400">Instagram</span>
          <a href="https://instagram.com/vacilada_mx" target="_blank" class="text-neon hover:underline font-mono">@vacilada_mx →</a>
        </div>
        <div class="flex justify-between py-2 border-b border-white/5">
          <span class="text-gray-400">TikTok (sugerido)</span>
          <span class="text-gray-500 font-mono">@vacilada_mx</span>
        </div>
        <div class="flex justify-between py-2">
          <span class="text-gray-400">Facebook</span>
          <span class="text-gray-500 font-mono">página existente · slug TBD</span>
        </div>
      </div>
    </UiCard>

    <!-- Useful links -->
    <UiCard title="Links útiles">
      <div class="space-y-1 text-sm">
        <div class="flex justify-between py-2 border-b border-white/5">
          <span class="text-gray-400">Press kit público</span>
          <a href="/press" target="_blank" class="text-neon hover:underline">/press →</a>
        </div>
        <div class="flex justify-between py-2 border-b border-white/5">
          <span class="text-gray-400">Todas las bromas (SEO)</span>
          <a href="/bromas" target="_blank" class="text-neon hover:underline">/bromas →</a>
        </div>
        <div class="flex justify-between py-2 border-b border-white/5">
          <span class="text-gray-400">Sitemap XML</span>
          <a href="/sitemap.xml" target="_blank" class="text-neon hover:underline">/sitemap.xml →</a>
        </div>
        <div class="flex justify-between py-2 border-b border-white/5">
          <span class="text-gray-400">Watermark audio (outro)</span>
          <a href="/watermark.mp3" target="_blank" class="text-neon hover:underline">/watermark.mp3 →</a>
        </div>
        <div class="flex justify-between py-2">
          <span class="text-gray-400">Marketing playbook (repo)</span>
          <a href="https://github.com/ProIEZRush1/ECHJokes/tree/master/docs/marketing" target="_blank" class="text-neon hover:underline">GitHub →</a>
        </div>
      </div>
    </UiCard>
  </div>
</template>

<script setup>
import { Download } from 'lucide-vue-next'
import UiCard from '../components/UiCard.vue'
import UiButton from '../components/UiButton.vue'

const banners = [
  { file: 'banner-landscape.png', label: 'Landscape', size: '1730×909' },
  { file: 'banner-story.png',     label: 'Story',     size: '941×1672' },
  { file: 'banner-whatsapp.png',  label: 'WhatsApp',  size: '1254×1254' },
]

const social = [
  { file: 'profile-pic.png',   label: 'Profile pic',         size: '1024×1024' },
  { file: 'fb-cover.png',      label: 'Facebook cover',      size: '1536×1024' },
  { file: 'post-pricing.png',  label: 'Post · pricing $35',  size: '1024×1024' },
  { file: 'post-howto.png',    label: 'Post · how it works', size: '1024×1024' },
  { file: 'post-sat.png',      label: 'Post · escenario SAT', size: '1024×1024' },
  { file: 'post-pizza.png',    label: 'Post · pizza 03:47',  size: '1024×1024' },
  { file: 'post-reaction.png', label: 'Post · antes/después', size: '1024×1024' },
  { file: 'post-quote.png',    label: 'Post · testimonial',  size: '1024×1024' },
  { file: 'post-viral.png',    label: 'Post · top 5 semana', size: '1024×1024' },
  { file: 'post-referral.png', label: 'Post · +2 créditos',  size: '1024×1024' },
  { file: 'post-contest.png',  label: 'Post · radio contest', size: '1024×1024' },
  { file: 'post-launch.png',   label: 'Post · launch',       size: '1024×1024' },
  { file: 'post-suegra.png',      label: 'Post · suegra & lotería',  size: '1024×1024' },
  { file: 'post-jefe.png',        label: 'Post · renuncio al jefe',  size: '1024×1024' },
  { file: 'post-veterinario.png', label: 'Post · veterinario',       size: '1024×1024' },
  { file: 'post-banco.png',       label: 'Post · banco / fraude',    size: '1024×1024' },
  { file: 'post-cfe.png',         label: 'Post · CFE recibo',        size: '1024×1024' },
  { file: 'post-policia.png',     label: 'Post · policía / multa',   size: '1024×1024' },
  { file: 'post-doctor.png',      label: 'Post · doctor',            size: '1024×1024' },
  { file: 'post-tipos.png',       label: 'Post · 5 tipos víctimas',  size: '1024×1024' },
  { file: 'post-mockup.png',      label: 'Post · app mockup',        size: '1024×1024' },
  { file: 'post-stats.png',       label: 'Post · stats / la neta',   size: '1024×1024' },
]
</script>
