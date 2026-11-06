<header class="topbar" id="top"><div class="container topbar-inner"><a aria-label="{{ $text['logo_aria'] }}" class="logo" href="{{ $url('/') }}"><img alt="Salam Sesame" height="55" src="/landing/img-01.svg" width="174"/></a>
<nav aria-label="{{ $text['nav_aria'] }}" class="desktop-nav">
@foreach ($navLinks as $k)
<a class="nav-link{{ $k === $page['key'] ? ' active' : '' }}" href="{{ $url($k === 'index' ? '/' : '/'.$k) }}">{{ $t($pages[$k]['title']) }}</a>
@endforeach
</nav>
<div class="header-actions"><div class="lang-switch" aria-label="{{ $text['lang_aria'] }}">
@foreach ($langs as $l)
<a data-lang="{{ $l }}" href="{{ $langUrl($path, $l) }}"@if ($l === $lang) class="active"@endif>{{ strtoupper($l) }}</a>
@endforeach
</div>
<a class="head-contact" href="{{ $url('/contact') }}">{{ $text['contact_label'] }}</a>
<a class="btn btn-green head-cta" href="{{ $url('/contact') }}">{{ $text['cta_label'] }} {!! $icon('arrow', 16) !!}</a>
<button aria-controls="mobile-menu" aria-expanded="false" aria-label="{{ $text['menu_open'] }}" class="nav-toggle">{!! $icon('menu', 24) !!}</button></div>
</div>
<nav aria-label="{{ $text['mobile_nav_aria'] }}" class="mobile-menu" hidden="" id="mobile-menu">
@foreach ($navLinks as $k)
<a class="nav-link{{ $k === $page['key'] ? ' active' : '' }}" href="{{ $url($k === 'index' ? '/' : '/'.$k) }}">{{ $t($pages[$k]['title']) }}</a>
@endforeach
<a href="{{ $url('/contact') }}">{{ $text['contact_label'] }}</a>
<a class="btn btn-green" href="{{ $url('/contact') }}">{{ $text['cta_label'] }} {!! $icon('arrow', 17) !!}</a>
</nav>
</header>
