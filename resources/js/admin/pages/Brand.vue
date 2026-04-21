<template>
  <div class="max-w-5xl mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold text-white mb-2">Brand & Press</h1>
    <p class="text-gray-400 mb-6">Todos los assets de marca y links útiles para marketing.</p>

    <div class="grid md:grid-cols-2 gap-4 mb-6">
      <div class="bg-matrix-800 border border-matrix-600 rounded-xl p-5">
        <h2 class="text-sm font-semibold text-gray-400 uppercase mb-3">Logo</h2>
        <div class="bg-matrix-900 rounded-lg p-6 flex items-center justify-center mb-3">
          <img src="/brand/logo.svg" alt="Vacilada logo" class="w-32 h-32" />
        </div>
        <a href="/brand/logo.svg" download class="block text-center w-full py-2 rounded-lg bg-neon text-matrix-900 font-semibold text-sm hover:shadow-neon transition">Descargar SVG</a>
      </div>

      <div class="bg-matrix-800 border border-matrix-600 rounded-xl p-5">
        <h2 class="text-sm font-semibold text-gray-400 uppercase mb-3">Wordmark</h2>
        <div class="bg-matrix-900 rounded-lg p-6 flex items-center justify-center mb-3" style="min-height:134px">
          <img src="/brand/wordmark.svg" alt="Vacilada wordmark" class="max-w-full h-auto" />
        </div>
        <a href="/brand/wordmark.svg" download class="block text-center w-full py-2 rounded-lg bg-neon text-matrix-900 font-semibold text-sm hover:shadow-neon transition">Descargar SVG</a>
      </div>
    </div>

    <div class="bg-matrix-800 border border-matrix-600 rounded-xl p-5 mb-6">
      <h2 class="text-sm font-semibold text-gray-400 uppercase mb-3">OG image (para redes sociales)</h2>
      <div class="bg-matrix-900 rounded-lg p-4 mb-3">
        <img src="/brand/og-image.svg" alt="OG image" class="w-full rounded" />
      </div>
      <a href="/brand/og-image.svg" download class="block text-center w-full py-2 rounded-lg bg-neon text-matrix-900 font-semibold text-sm hover:shadow-neon transition">Descargar 1200×630 SVG</a>
    </div>

    <div class="bg-matrix-800 border border-matrix-600 rounded-xl p-5 mb-6">
      <h2 class="text-sm font-semibold text-gray-400 uppercase mb-3">Paleta</h2>
      <div class="grid grid-cols-4 gap-3">
        <div class="rounded-lg p-4 text-center" style="background:#39FF14;color:#0a0f0a;font-weight:600">
          <div>Neón</div><code class="text-xs">#39FF14</code>
        </div>
        <div class="rounded-lg p-4 text-center bg-matrix-900 border border-matrix-700">
          <div class="text-white">Matrix 900</div><code class="text-xs text-gray-500">#0a0f0a</code>
        </div>
        <div class="rounded-lg p-4 text-center bg-matrix-800 border border-matrix-700">
          <div class="text-white">Matrix 800</div><code class="text-xs text-gray-500">#111a11</code>
        </div>
        <div class="rounded-lg p-4 text-center bg-matrix-700 border border-matrix-700">
          <div class="text-white">Matrix 700</div><code class="text-xs text-gray-500">#1a2a1a</code>
        </div>
      </div>
    </div>

    <div class="bg-matrix-800 border border-matrix-600 rounded-xl p-5">
      <h2 class="text-sm font-semibold text-gray-400 uppercase mb-3">Links útiles</h2>
      <div class="space-y-2 text-sm">
        <div class="flex justify-between py-2 border-b border-matrix-700">
          <span class="text-gray-400">Press kit público</span>
          <a href="/press" target="_blank" class="text-neon hover:underline">/press →</a>
        </div>
        <div class="flex justify-between py-2 border-b border-matrix-700">
          <span class="text-gray-400">Todas las bromas (SEO)</span>
          <a href="/bromas" target="_blank" class="text-neon hover:underline">/bromas →</a>
        </div>
        <div class="flex justify-between py-2 border-b border-matrix-700">
          <span class="text-gray-400">Sitemap XML</span>
          <a href="/sitemap.xml" target="_blank" class="text-neon hover:underline">/sitemap.xml →</a>
        </div>
        <div class="flex justify-between py-2 border-b border-matrix-700">
          <span class="text-gray-400">Watermark audio (outro)</span>
          <a href="/watermark.mp3" target="_blank" class="text-neon hover:underline">/watermark.mp3 →</a>
        </div>
        <div class="flex justify-between py-2">
          <span class="text-gray-400">Marketing playbook (repo)</span>
          <a href="https://github.com/ProIEZRush1/ECHJokes/tree/master/docs/marketing" target="_blank" class="text-neon hover:underline">GitHub →</a>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
</script>
