<section class="benefit-bar"><div class="container benefit-grid">
@foreach ($s['items'] as $item)
<div class="benefit"><span class="benefit-icon">{!! $icon($item['icon'], 22) !!}</span><div><h3>{{ $t($item['title']) }}</h3>
<p>{{ $t($item['text']) }}</p>
</div>
</div>
@endforeach
</div>
</section>
