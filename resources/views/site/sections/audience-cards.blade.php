<section class="section container">@include('site.parts.heading')
<div class="audience-cards">
@foreach ($s['items'] as $item)
<article class="audience-card"><img loading="lazy" decoding="async" alt="{{ $t($item['image_alt']) }}" src="{{ $item['image'] }}"/><div><h3>{{ $t($item['title']) }}</h3>
<p>{{ $t($item['text']) }}</p>
<a class="text-link" href="{{ $url($item['link']['href']) }}">{{ $t($item['link']['label']) }} {!! $icon('arrow', 16) !!}</a>
</div>
</article>
@endforeach
</div>
</section>
