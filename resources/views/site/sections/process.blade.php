<section class="section container">@include('site.parts.heading')
<div class="process-timeline">
@foreach ($s['items'] as $item)
<article class="process-row"><span class="process-number">{{ sprintf('%02d', $loop->iteration) }}</span><div class="process-main"><h2>{{ $t($item['title']) }}</h2>
<p>{{ $t($item['text']) }}</p>
<a class="text-link" href="{{ $url($item['link']['href']) }}">{{ $t($item['link']['label']) }} {!! $icon('arrow', 16) !!}</a>
</div>
<div class="process-photo"><img loading="lazy" decoding="async" alt="{{ $t($item['image_alt']) }}" src="{{ $item['image'] }}"/></div>
</article>
@endforeach
</div>
<div class="process-note">{!! $icon('shield', 22) !!} <p>{!! $html($s['note']) !!}</p>
</div>
</section>
