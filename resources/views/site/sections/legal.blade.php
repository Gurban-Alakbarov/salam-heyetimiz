<section class="section legal-page container"><aside class="legal-sidebar"><h3>{{ $text['legal_sidebar_title'] }}</h3>
@foreach ($site['legal_pages'] as $k)
<a class="{{ $k === $page['key'] ? 'current' : '' }}" href="{{ $url('/'.$k) }}">{{ $t($pages[$k]['title']) }} {!! $icon('chevron', 15) !!}</a>
@endforeach
<div class="legal-support">{!! $icon('headset', 22) !!}<b>{{ $text['legal_support_title'] }}</b><p>{{ $text['legal_support_text'] }}</p>
<a href="{{ $url('/contact') }}">{{ $text['legal_support_link'] }} {!! $icon('arrow', 16) !!}</a>
</div>
</aside><article class="legal-paper"><div class="legal-metadata"><span>{{ $t($s['company']) }}</span></div>
<div class="legal-content">
@foreach ($s['items'] as $item)
<section><h2 id="s{{ $loop->iteration }}">{{ $t($item['title']) }}</h2>
{!! $html($item['body']) !!}
</section>
@endforeach
<p style="margin-top:35px"><a class="btn btn-secondary" href="{{ $url($s['button']['href']) }}">{{ $t($s['button']['label']) }} <svg aria-hidden="true" class="icon"><use href="#i-arrow"></use></svg></a>
</p>
</div>
</article>
</section>
