<section class="section container home-faq">@include('site.parts.heading')
<div class="home-faq-grid"><div class="faq-compact">
@foreach ($s['items'] as $item)
<details><summary>{{ $t($item['q']) }}</summary><p>{{ $t($item['a']) }}</p>
</details>
@endforeach
</div>
<div class="faq-side"><span>{!! $icon('headset', 30) !!}</span><h3>{{ $t($s['side_title']) }}</h3>
<p>{{ $t($s['side_text']) }}</p>
<a class="btn btn-green" href="{{ $url($s['side_button']['href']) }}">{{ $t($s['side_button']['label']) }} {!! $icon('arrow', 16) !!}</a>
</div>
</div>
<div class="section-link"><a class="text-link" href="{{ $url($s['link']['href']) }}">{{ $t($s['link']['label']) }} {!! $icon('arrow', 19) !!}</a>
</div>
</section>
