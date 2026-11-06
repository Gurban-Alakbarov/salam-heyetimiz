<section class="section container"@if (! empty($s['id'])) id="{{ $s['id'] }}"@endif>@include('site.parts.heading')
<div class="products-grid">
@foreach ($s['items'] as $item)
<a class="image-product" href="{{ $url($item['href']) }}"><img loading="lazy" decoding="async" alt="{{ $t($item['image_alt']) }}" src="{{ $item['image'] }}"/><div class="image-product-copy"><h3>{{ $t($item['title']) }}</h3>
<p>{{ $t($item['text']) }}</p>
</div>
</a>
@endforeach
</div>
<div class="section-link"><a class="text-link" href="{{ $url($s['link']['href']) }}">{{ $t($s['link']['label']) }} {!! $icon('arrow', 19) !!}</a>
</div>
</section>
