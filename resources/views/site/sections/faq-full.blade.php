<section class="section container"><div class="faq-layout"><div class="faq-stack">
@foreach ($s['items'] as $item)
<details class="faq-item"><summary>{{ $t($item['q']) }}<span>+</span></summary><p>{{ $t($item['a']) }}</p>
</details>
@endforeach
</div>
<aside class="faq-support"><div class="support-icon">{!! $icon('headset', 29) !!}</div>
<h3>{{ $t($s['side_title']) }}</h3>
<p>{{ $t($s['side_text']) }}</p>
<a class="btn btn-green" href="{{ $url($s['side_button']['href']) }}">{{ $t($s['side_button']['label']) }} {!! $icon('arrow', 18) !!}</a>
</aside></div>
</section>
