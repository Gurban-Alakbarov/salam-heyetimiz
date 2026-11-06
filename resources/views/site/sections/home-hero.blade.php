@php $ph = $s['phone']; @endphp
<section class="home-hero"><div class="container hero-layout"><div class="hero-copy"><span class="label-line"><span></span> {{ $t($s['kicker']) }}</span><h1>{!! $html($s['title']) !!}</h1>
<p>{{ $t($s['text']) }}</p>
<div class="hero-actions"><a class="btn btn-green" href="{{ $url($s['primary']['href']) }}">{{ $t($s['primary']['label']) }} {!! $icon('arrow', 18) !!}</a>
<a class="btn btn-outline" href="{{ $url($s['secondary']['href']) }}">{{ $t($s['secondary']['label']) }} {!! $icon('chevron', 15) !!}</a>
</div>
<div class="hero-smallprint">{{ $t($s['smallprint']) }}</div>
</div>
<div class="hero-image"><img fetchpriority="high" decoding="async" alt="{{ $t($s['image_alt']) }}" src="{{ $s['image'] }}"/><div class="hero-soft-overlay"></div>
<div class="phone-overlay"><div aria-label="{{ $t($ph['aria']) }}" class="device-frame"><div class="screen"><div class="phone-top"><b>9:41</b><span>● ▰ ▰</span></div>
<div class="phone-name">{{ $t($ph['title']) }} <span>{!! $icon('bell', 16) !!}</span></div>
<div class="phone-label">{{ $t($ph['list_label']) }}</div>
<div class="mock-device"><div class="mock-visual"><img loading="lazy" decoding="async" alt="{{ $t($ph['device_image_alt']) }}" src="{{ $ph['device_image'] }}"/><span class="mock-online"><i></i> {{ $t($ph['online']) }}</span></div>
<div class="mock-device-inner"><strong>{{ $t($ph['device_name']) }}</strong><p>{{ $t($ph['device_place']) }}</p>
<div class="mock-links"><span>{!! $icon('users', 13) !!} {{ $t($ph['invite']) }}</span><span>{!! $icon('map', 13) !!} {{ $t($ph['directions']) }}</span></div>
<button class="mock-control" data-demo-pult="" type="button">{!! $icon('key', 16) !!} {{ $t($ph['remote']) }}</button><div aria-live="polite" class="mock-response" hidden="">{{ $t($ph['response']) }}</div>
</div>
</div>
<div class="mock-device mock-small"><span class="mock-icon">{!! $icon('garage', 22) !!}</span><div><strong>{{ $t($ph['small_name']) }}</strong><p>{{ $t($ph['small_place']) }}</p>
</div>
<span class="mock-dot"></span></div>
<div class="phone-bottom"><span>{!! $icon('home', 16) !!} {{ $t($ph['tab_home']) }}</span><span class="mock-selected">{!! $icon('phone', 16) !!} {{ $t($ph['tab_devices']) }}</span><span>{!! $icon('users', 16) !!} {{ $t($ph['tab_profile']) }}</span></div>
</div>
</div>
</div>
<div class="hero-message"><span class="hero-message-icon">{!! $icon('phone', 18) !!}</span><div><b>{{ $t($s['badge_title']) }}</b><span>{{ $t($s['badge_text']) }}</span></div>
</div>
</div>
</div>
</section>
