<section class="section container"><div class="contact-layout"><div class="contact-photo"><img loading="lazy" decoding="async" alt="{{ $t($s['image_alt']) }}" src="{{ $s['image'] }}"/><div class="contact-photo-copy"><span>{{ $t($s['photo_kicker']) }}</span><h2>{!! $html($s['photo_title']) !!}</h2>
<p>{{ $t($s['photo_text']) }}</p>
</div>
</div>
<div class="form-card"><span class="kicker">{{ $t($s['kicker']) }}</span><h2>{!! $html($s['title']) !!}</h2>
<p>{{ $t($s['text']) }}</p>
<form id="request-form"><input aria-hidden="true" autocomplete="off" name="website" style="position:absolute;left:-9999px" tabindex="-1"/><div class="field-pair"><label>{{ $t($s['name_label']) }} <b>*</b><input autocomplete="name" name="name" placeholder="{{ $t($s['name_hint']) }}" required=""/></label><label>{{ $t($s['email_label']) }} <b>*</b><input autocomplete="email" name="email" placeholder="{{ $t($s['email_hint']) }}" required="" type="email"/></label></div>
<div class="field-pair"><label>{{ $t($s['phone_label']) }} <b>*</b><input autocomplete="tel" name="phone" placeholder="{{ $t($s['phone_hint']) }}" required="" type="tel"/></label><label>{{ $t($s['place_label']) }} <b>*</b><select name="place" required=""><option value="">{{ $t($s['place_hint']) }}</option>@foreach ($s['places'] as $o)<option>{{ $t($o['text']) }}</option>@endforeach</select></label></div>
<label>{{ $t($s['topic_label']) }}<select name="topic">@foreach ($s['topics'] as $o)<option>{{ $t($o['text']) }}</option>@endforeach</select></label><label>{{ $t($s['message_label']) }}<textarea name="message" placeholder="{{ $t($s['message_hint']) }}" rows="5"></textarea></label><label class="consent"><input required="" type="checkbox"/><span>{!! $html($s['consent']) !!}</span></label><button class="btn btn-green btn-wide" type="submit">{{ $t($s['submit']) }} {!! $icon('arrow', 17) !!}</button></form><div class="result" hidden="" id="request-result"><strong>{{ $t($s['done_title']) }}</strong><p>{{ $t($s['done_text']) }}</p>
</div>
</div>
</div>
</section>
