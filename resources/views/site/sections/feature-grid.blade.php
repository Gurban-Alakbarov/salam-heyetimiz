<section class="section pale-section"><div class="container">@include('site.parts.heading')
<div class="functions-grid">
@foreach ($s['items'] as $item)
<div class="function"><div class="function-symbol">{!! $icon($item['icon'], 27) !!}</div>
<h3>{{ $t($item['title']) }}</h3>
<p>{{ $t($item['text']) }}</p>
</div>
@endforeach
</div>
</div>
</section>
