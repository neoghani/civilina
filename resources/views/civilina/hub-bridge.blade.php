<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Civilina</title>
<style>
:root{--ink:#102235;--muted:#6b7b89;--bg:#f5f7f8;--line:#dce3e8;--navy:#11354d}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);font-family:Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",Arial,sans-serif;color:var(--ink)}
a{color:inherit}
.top{height:58px;background:#fff;border-bottom:1px solid var(--line);display:flex;align-items:center;justify-content:space-between;padding:0 22px}
.brand{font-weight:850;letter-spacing:.02em}
main{max-width:1120px;margin:auto;padding:34px 22px 60px}
.eyebrow{font-size:11px;color:var(--muted);letter-spacing:.08em;text-transform:uppercase}
h1{font-size:32px;margin:4px 0 6px}
.muted{color:var(--muted)}
.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-top:24px}
.card{background:#fff;border:1px solid var(--line);border-radius:10px;padding:17px;text-decoration:none;min-height:116px}
.card:hover{border-color:#9db0bc}
.card strong{display:block;font-size:17px;margin-bottom:6px}
.route{margin-top:13px;font-family:ui-monospace,SFMono-Regular,Consolas,monospace;font-size:10px;color:#8795a0}
.btn{display:inline-flex;padding:7px 10px;border:1px solid var(--line);border-radius:7px;background:#fff;text-decoration:none;font-size:12px;font-weight:700}
@media(max-width:900px){.grid{grid-template-columns:1fr 1fr}}
@media(max-width:600px){
    .top{height:52px;padding:0 13px}
    main{padding:23px 13px 45px}
    h1{font-size:27px}
    .grid{grid-template-columns:1fr;gap:9px}
    .card{min-height:92px;padding:14px}
}
</style>
@include('components.civilina-command-theme')
</head>
<body>
<header class="top">
<div class="brand">CIVILINA</div>
@if(\Illuminate\Support\Facades\Route::has('engineering.home.bridge'))
<a class="btn" href="{{ route('engineering.home.bridge',['organization'=>$organization,'project'=>$project]) }}">Ingénierie</a>
@endif
</header>

<main>
<div class="eyebrow">Accueil Civilina</div>
<h1>Modules</h1>
<div class="muted">
    Accès aux blocs disponibles. Cette page n’invente aucun module : elle affiche uniquement les routes réellement présentes.
</div>

<div class="grid">
@forelse($modules as $module)
<a class="card" href="{{ $module['url'] }}">
    <strong>{{ $module['label'] }}</strong>
    <div class="muted">{{ $module['description'] }}</div>
    <div class="route">{{ $module['route'] }}</div>
</a>
@empty
<div class="card">
    <strong>Ingénierie</strong>
    <div class="muted">Aucun autre bloc détecté.</div>
</div>
@endforelse
</div>
</main>
</body>
</html>
