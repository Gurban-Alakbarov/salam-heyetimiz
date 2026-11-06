<section class="section container">@include('site.parts.heading')
<div class="service-grid">
@foreach ($s['items'] as $item)
<article class="service-card"><img loading="lazy" decoding="async" alt="{{ $t($item['image_alt']) }}" src="{{ $item['image'] }}"/><div><span class="service-index">{{ sprintf('%02d', $loop->iteration) }}</span><h3>{{ $t($item['title']) }}</h3>
<p>{{ $t($item['text']) }}</p>
</div>
</article>
@endforeach
</div>
</section>
