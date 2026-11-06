<section class="section container centered-final"><h2>{!! $html($s['title']) !!}</h2>
<p>{{ $t($s['text']) }}</p>
<a class="btn btn-green" href="{{ $url($s['button']['href']) }}">{{ $t($s['button']['label']) }} {!! $icon('arrow', 18) !!}</a>
</section>
