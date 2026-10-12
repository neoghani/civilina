<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Civilina Engineering · Workspace</title>
<style>
:root{--navy:#0d3148;--ink:#102235;--muted:#687b8c;--bg:#f3f6f8;--line:#d8e1e7}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);font-family:Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",Arial,sans-serif;color:var(--ink)}
header{height:60px;background:#fff;border-bottom:1px solid var(--line);display:flex;align-items:center;justify-content:space-between;padding:0 24px}
main{padding:28px;max-width:1200px;margin:auto}
h1{margin:4px 0 6px;font-size:30px}
.muted{color:var(--muted)}
.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-top:24px}
.card{background:#fff;border:1px solid var(--line);border-radius:12px;padding:18px;text-decoration:none;color:inherit;min-height:118px}
.card:hover{border-color:#8aa3b4;box-shadow:0 4px 14px rgba(16,47,70,.07)}
.card strong{display:block;font-size:18px;margin-bottom:8px}
.route{font-family:ui-monospace,SFMono-Regular,Consolas,monospace;font-size:11px;color:var(--muted)}
.btn{display:inline-flex;padding:8px 11px;border:1px solid var(--line);border-radius:8px;background:#fff;text-decoration:none;color:inherit;font-weight:650;font-size:12px}
@media(max-width:900px){.grid{grid-template-columns:1fr 1fr}}
@media(max-width:620px){.grid{grid-template-columns:1fr}}
</style>
@include('components.civilina-command-theme')
</head>
<body>
@include('engineering._engineering-nav')

<header>
<strong>CIVILINA ENGINEERING · WORKSPACE</strong>
@if(\Illuminate\Support\Facades\Route::has('engineering.normative-library.index'))
<a class="btn" href="{{ route('engineering.normative-library.index',['organization'=>$organization,'project'=>$project]) }}">Bibliothèque normative</a>
@endif
</header>
<main>
<div class="muted" style="font-size:11px;text-transform:uppercase;letter-spacing:.08em">Navigation projet</div>
<h1>Workspace Engineering</h1>
<div class="muted">
    Point d’accès stable aux modules disponibles du projet. Les liens sont détectés à partir des routes réellement présentes dans Laravel.
</div>

<div class="grid">
@forelse($links as $link)
<a class="card" href="{{ $link['url'] }}">
    <strong>{{ $link['label'] }}</strong>
    <div class="muted">Ouvrir le module</div>
    <div class="route">{{ $link['route'] }}</div>
</a>
@empty
<div class="card">
    <strong>Aucun module détecté</strong>
    <div class="muted">Les routes Engineering ne sont pas encore disponibles.</div>
</div>
@endforelse
</div>
</main>
</body>
</html>
