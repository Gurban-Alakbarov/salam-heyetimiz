<footer class="footer"><div class="container footer-grid"><div class="footer-about"><a class="footer-logo" href="{{ $url('/') }}"><img alt="Salam Sesame" src="/landing/logo-light.svg" width="170"/></a>
<p>{!! $html($site['texts']['footer_about']) !!}</p>
<small>{{ $text['footer_company'] }}</small></div>
<div><h3>{{ $text['footer_site_title'] }}</h3>
@foreach ($site['footer_pages'] as $k)
@isset($pages[$k])
<a href="{{ $url('/'.$k) }}">{{ $t($pages[$k]['title']) }}</a>
@endisset
@endforeach
<a href="{{ $url('/contact') }}">{{ $text['footer_contact_item'] }}</a>
</div>
<div><h3>{{ $text['footer_legal_title'] }}</h3>
@foreach ($site['legal_pages'] as $k)
<a href="{{ $url('/'.$k) }}">{{ $t($pages[$k]['title']) }}</a>
@endforeach
</div>
<div><h3>{{ $text['footer_contact_title'] }}</h3>
<p>{{ $text['footer_contact_text'] }}</p>
<a class="footer-action" href="{{ $url('/contact') }}">{{ $text['footer_action'] }} {!! $icon('arrow', 16) !!}</a>
</div>
</div>
<div class="container foot-bottom"><span>{{ $text['copyright'] }}</span><span>{{ $text['region'] }}</span></div>
</footer>
