@props(['slot', 'format' => 'auto'])

<div class="w-full flex justify-center my-6 {{ $format === 'leaderboard' ? 'hidden md:flex' : '' }}">
    <div class="w-full max-w-[728px] overflow-hidden">
        <ins class="adsbygoogle"
             style="display:block"
             data-ad-client="ca-pub-7978976781623579"
             data-ad-slot="{{ $slot }}"
             data-ad-format="auto"
             data-full-width-responsive="true"
        ></ins>
        <script>(adsbygoogle = window.adsbygoogle || []).push({});</script>
    </div>
</div>
