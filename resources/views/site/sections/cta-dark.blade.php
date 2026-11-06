<section class="section dark-offer"><div class="container offer-layout"><div><span class="kicker kicker-light">{{ $t($s['kicker']) }}</span><h2>{!! $html($s['title']) !!}</h2>
<p>{{ $t($s['text']) }}</p>
</div>
<a class="btn btn-white" href="{{ $url($s['button']['href']) }}">{{ $t($s['button']['label']) }} {!! $icon('arrow', 18) !!}</a>
</div>
</section>
