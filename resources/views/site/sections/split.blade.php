<section class="section container audience-intro"><div class="split-image"><img loading="lazy" decoding="async" alt="{{ $t($s['image_alt']) }}" src="{{ $s['image'] }}"/></div>
<div class="split-copy"><span class="kicker">{{ $t($s['kicker']) }}</span><h2>{!! $html($s['title']) !!}</h2>
<p>{{ $t($s['text']) }}</p>
<div class="checkpoints">@foreach ($s['checks'] as $item)<span>{!! $icon('check', 18) !!} {{ $t($item['text']) }}</span>@endforeach</div>
<a class="btn btn-outline" href="{{ $url($s['button']['href']) }}">{{ $t($s['button']['label']) }} {!! $icon('arrow', 18) !!}</a>
</div>
</section>
