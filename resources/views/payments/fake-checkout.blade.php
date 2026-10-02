<!doctype html>
<html lang="az">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex">
<title>TEST ÖDƏNİŞ — Salam Həyətimiz</title>
<style>
body{font-family:system-ui,-apple-system,sans-serif;background:#f8fafc;margin:0;color:#0f172a}
.banner{background:#b91c1c;color:#fff;text-align:center;font-weight:800;letter-spacing:.08em;padding:14px 16px;font-size:18px}
.banner small{display:block;font-weight:500;letter-spacing:0;font-size:13px;opacity:.9;margin-top:4px}
.wrap{max-width:420px;margin:24px auto;padding:0 16px}
.card{background:#fff;border-radius:16px;padding:24px;box-shadow:0 8px 30px rgba(0,0,0,.08)}
.row{display:flex;justify-content:space-between;margin:8px 0;color:#475569}.row b{color:#0f172a}
.amount{font-size:32px;font-weight:800;text-align:center;margin:16px 0 4px}
form{margin:10px 0}
button{width:100%;border:0;border-radius:10px;padding:14px;font-size:16px;font-weight:700;cursor:pointer;color:#fff}
.pay{background:#16a34a}.decline{background:#dc2626}.cancel{background:#64748b}.pending{background:#2563eb}
.note{font-size:12px;color:#64748b;text-align:center;margin-top:16px}
a{color:#2563eb}
</style>
</head>
<body>
<div class="banner">TEST ÖDƏNİŞ<small>Bu, simulyasiya olunmuş ödəniş səhifəsidir — real pul köçürülmür.</small></div>
<div class="wrap"><div class="card">
  <div class="amount">{{ $amount }} {{ $order->currency }}</div>
  <div class="row"><span>Sifariş</span><b>{{ $order->reference }}</b></div>
  <div class="row"><span>Təyinat</span><b>{{ $order->purpose->value }}</b></div>
  <div class="row"><span>Status</span><b>{{ $order->status->value }}</b></div>

  @if ($settled)
    <p class="note">Bu sifariş artıq yekunlaşıb. <a href="{{ $returnUrl }}">Nəticəyə bax</a></p>
  @else
    <form method="post" action="{{ $actions['pay'] }}"><button class="pay" type="submit">Ödə (TEST)</button></form>
    <form method="post" action="{{ $actions['decline'] }}"><button class="decline" type="submit">Rədd et</button></form>
    <form method="post" action="{{ $actions['cancel'] }}"><button class="cancel" type="submit">Ləğv et</button></form>
    <form method="post" action="{{ $actions['pending'] }}"><button class="pending" type="submit">Gözlət (pending)</button></form>
  @endif

  <p class="note">TEST ÖDƏNİŞ · Kart məlumatı tələb olunmur və heç bir bank sorğusu göndərilmir.</p>
</div></div>
</body>
</html>
