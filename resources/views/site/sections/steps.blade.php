<section class="section pale-section"><div class="container">@include('site.parts.heading')
<div class="steps-grid">
@foreach ($s['items'] as $item)
<div class="mini-step"><span class="step-ball">{{ sprintf('%02d', $loop->iteration) }}</span><h3>{{ $t($item['title']) }}</h3>
<p>{{ $t($item['text']) }}</p>
</div>
@endforeach
</div>
<div class="section-link"><a class="text-link" href="{{ $url($s['link']['href']) }}">{{ $t($s['link']['label']) }} {!! $icon('arrow', 19) !!}</a>
</div>
</div>
</section>
