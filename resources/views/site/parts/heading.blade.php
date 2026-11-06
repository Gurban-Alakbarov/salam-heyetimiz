<div class="section-heading"><div class="kicker">{{ $t($s['kicker']) }}</div>
<h2>{!! $html($s['title']) !!}</h2>
@if ($t($s['text']) !== '')
<p>{{ $t($s['text']) }}</p>
@endif
</div>
