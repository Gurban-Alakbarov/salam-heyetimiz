@if ($s['items'] !== [])
<section class="section container"><div class="audience-cards">
@foreach ($s['items'] as $item)
<article class="audience-card"><img loading="lazy" decoding="async" alt="{{ $t($item['image_alt']) }}" src="{{ $item['image'] }}"/><div><h3>{{ $t($item['title']) }}</h3>
<p>{{ $t($item['text']) }}</p>
@if (! empty($item['ends_at']))
<p><strong>{{ $t($s['until_label']) }}</strong> {{ \Illuminate\Support\Carbon::parse($item['ends_at'])->format('d.m.Y') }}</p>
@endif
@if (! empty($item['link']['href']))
<a class="text-link" href="{{ $url($item['link']['href']) }}">{{ $t($item['link']['label']) }} {!! $icon('arrow', 16) !!}</a>
@endif
</div>
</article>
@endforeach
</div>
</section>
@endif
