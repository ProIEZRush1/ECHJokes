@props(['slot', 'format' => 'auto'])

<div class="w-full flex justify-center my-6 {{ $format === 'leaderboard' ? 'hidden md:flex' : '' }}">
    <div class="w-full max-w-[728px] border border-white/5 rounded-xl bg-white/[0.02] overflow-hidden">
        <ins class="adsbygoogle"
             style="{{ $format === 'leaderboard' ? 'display:inline-block;width:728px;height:90px' : 'display:block' }}"
             data-ad-client="ca-pub-7978976781623579"
             data-ad-slot="{{ $slot }}"
             @if($format === 'auto') data-ad-format="auto" data-full-width-responsive="true" @endif
        ></ins>
        <script>(adsbygoogle = window.adsbygoogle || []).push({});</script>
        <p class="text-[9px] text-gray-600 text-center py-0.5">Publicidad</p>
    </div>
</div>
