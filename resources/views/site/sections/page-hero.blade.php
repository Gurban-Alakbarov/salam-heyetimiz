<section class="page-hero"><div class="container page-hero-inner"><div><span class="kicker">{{ $t($s['kicker']) }}</span><h1>{!! $html($s['title']) !!}</h1>
<p>{{ $t($s['text']) }}</p>
</div>
@if ($s['image'] !== '')
<img loading="lazy" decoding="async" alt="{{ $t($s['image_alt']) }}" src="{{ $s['image'] }}"/>
@endif
</div>
</section>
