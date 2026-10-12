<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Civilina Normative Librarian</title>
<style>
:root{--navy:#102f46;--ink:#102235;--muted:#687b8c;--bg:#f3f6f8;--line:#d8e1e7;--soft:#edf4f8;--ok:#167458;--warn:#a96400;--bad:#b42318}
*{box-sizing:border-box}body{margin:0;background:var(--bg);font-family:Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",Arial,sans-serif;color:var(--ink)}
a{color:inherit}.top{height:58px;background:#fff;border-bottom:1px solid var(--line);display:flex;align-items:center;justify-content:space-between;padding:0 24px}
main{padding:24px 28px 60px}.head{display:flex;justify-content:space-between;gap:18px}.eyebrow{font-size:11px;color:var(--muted);letter-spacing:.1em;text-transform:uppercase}
h1{font-size:28px;margin:3px 0 5px}.muted{color:var(--muted)}.actions{display:flex;gap:8px;flex-wrap:wrap}
.btn{display:inline-flex;padding:9px 12px;border:1px solid var(--line);border-radius:8px;background:#fff;text-decoration:none;font-weight:650;font-size:13px;cursor:pointer}
.btn.primary{background:var(--navy);color:#fff;border-color:var(--navy)}.stats{display:grid;grid-template-columns:repeat(8,1fr);gap:9px;margin:16px 0}
.stat{display:block;background:#fff;border:1px solid var(--line);border-radius:9px;padding:12px;text-decoration:none}.stat:hover{border-color:#9eb3c2}.stat span{display:block;font-size:9px;text-transform:uppercase;color:var(--muted)}.stat strong{font-size:22px}.stat.warn{border-left:4px solid var(--warn)}.stat.bad{border-left:4px solid var(--bad)}.stat.ok{border-left:4px solid var(--ok)}
.panel{background:#fff;border:1px solid var(--line);border-radius:11px;padding:16px;margin-bottom:14px}.filters{display:grid;grid-template-columns:2fr repeat(5,1fr) auto;gap:8px}
input,select{width:100%;padding:9px 10px;border:1px solid var(--line);border-radius:7px;background:#fff}table{width:100%;border-collapse:collapse;font-size:13px;margin-top:12px}
th,td{padding:9px 8px;border-bottom:1px solid #edf0f2;text-align:left;vertical-align:top}th{font-size:10px;text-transform:uppercase;color:var(--muted)}
.code{font-family:ui-monospace,SFMono-Regular,Consolas,monospace;font-weight:700}.badge{display:inline-block;padding:3px 7px;border-radius:999px;background:var(--soft);font-size:11px}.badge.ok{background:#e5f4ee;color:var(--ok)}.badge.warn{background:#fff3dd;color:var(--warn)}.badge.bad{background:#fdebea;color:var(--bad)}
.message{padding:11px 13px;background:#eaf5ff;border:1px solid #bddcf5;border-radius:8px;margin:14px 0}
.toolbar{display:flex;justify-content:space-between;align-items:center;gap:12px;margin:12px 0;flex-wrap:wrap}
@media(max-width:1250px){.filters{grid-template-columns:1fr 1fr}.stats{grid-template-columns:repeat(4,1fr)}}
</style>
@include('components.civilina-command-theme')
</head>
<body>
@include('engineering._engineering-nav')

<header class="top">
<strong>CIVILINA ENGINEERING · NORMATIVE LIBRARIAN</strong>
<div class="actions">
@if(\Illuminate\Support\Facades\Route::has('engineering.normative-library.index'))
<a class="btn" href="{{ route('engineering.normative-library.index',['organization'=>$organization,'project'=>$project]) }}">Bibliothèque normative</a>
@endif
@if(\Illuminate\Support\Facades\Route::has('engineering.workspace'))
<a class="btn" href="{{ route('engineering.workspace.bridge',['organization'=>$organization,'project'=>$project]) }}">Workspace</a>
@endif
</div>
</header>

<main>
<div class="head">
<div>
<div class="eyebrow">Block 2K.3.3 · Review & Classification Engine</div>
<h1>Bibliothèque locale normative</h1>
<div class="muted">Classer → relire → valider → publier. Aucun original n’est déplacé.</div>
</div>
<div class="actions">
<form method="post" action="{{ route('engineering.normative-librarian.refine',['organization'=>$organization,'project'=>$project]) }}">@csrf<input type="hidden" name="limit" value="500"><input type="hidden" name="status" value="UNCLASSIFIED"><button class="btn primary">2e passe · 500 non classés</button></form>
<form method="post" action="{{ route('engineering.normative-librarian.export',['organization'=>$organization,'project'=>$project]) }}">@csrf<button class="btn">Exporter manifeste</button></form>
</div>
</div>

@if(session('normative_message'))<div class="message">{{ session('normative_message') }}</div>@endif

<div class="stats">
<a class="stat" href="{{ route('engineering.normative-librarian.index',['organization'=>$organization,'project'=>$project]) }}"><span>Tous</span><strong>{{ $counts['documents'] }}</strong></a>
<a class="stat ok" href="?status=AUTO_CLASSIFIED"><span>Auto classés</span><strong>{{ $counts['classified'] }}</strong></a>
<a class="stat warn" href="?status=TO_REVIEW"><span>À valider</span><strong>{{ $counts['review'] }}</strong></a>
<a class="stat bad" href="?status=UNCLASSIFIED"><span>Non classés</span><strong>{{ $counts['unclassified'] }}</strong></a>
<a class="stat ok" href="?status=VALIDATED"><span>Validés ingénieur</span><strong>{{ $counts['validated'] }}</strong></a>
<a class="stat warn" href="?special=ocr"><span>OCR requis</span><strong>{{ $counts['ocr'] }}</strong></a>
<a class="stat" href="?special=duplicates"><span>Doublons</span><strong>{{ $counts['duplicates'] }}</strong></a>
<a class="stat" href="?special=published"><span>Publiés</span><strong>{{ $counts['published'] }}</strong></a>
</div>

@include('engineering.normative-librarian._audit-panel')

@include('engineering.normative-librarian._physical-library-panel')

@include('engineering.normative-librarian._canonical-review-panel')

@include('engineering.normative-librarian._recovery-panel')

<section class="panel">
<div class="toolbar">
<div><h2 style="margin:0">Recherche et validation</h2><div class="muted">Clique sur un compteur pour ouvrir directement la file correspondante.</div></div>
<div class="actions">
<form method="post" action="{{ route('engineering.normative-librarian.refine',['organization'=>$organization,'project'=>$project]) }}">@csrf<input type="hidden" name="limit" value="500"><input type="hidden" name="status" value="TO_REVIEW"><button class="btn">Améliorer 500 « À valider »</button></form>
<form method="post" action="{{ route('engineering.normative-librarian.publish',['organization'=>$organization,'project'=>$project]) }}">@csrf<button class="btn">Publier les classés/validés</button></form>
</div>
</div>

<form method="get" class="filters">
<input name="q" value="{{ $q }}" placeholder="NF DTU 20.1, EN 206, GTR, maçonnerie...">
<select name="discipline"><option value="">Toutes disciplines</option>@foreach($disciplines as $row)<option value="{{ $row->discipline }}" @selected($discipline===$row->discipline)>{{ $row->discipline }} ({{ $row->total }})</option>@endforeach</select>
<select name="domain"><option value="">Tous domaines</option>@foreach($domains as $row)<option value="{{ $row->domain }}" @selected($domain===$row->domain)>{{ $row->domain }} ({{ $row->total }})</option>@endforeach</select>
<select name="subdomain"><option value="">Tous sous-domaines</option>@foreach($subdomains as $row)<option value="{{ $row->subdomain }}" @selected($subdomain===$row->subdomain)>{{ $row->subdomain }} ({{ $row->total }})</option>@endforeach</select>
<select name="status"><option value="">Tous statuts</option><option value="AUTO_CLASSIFIED" @selected($status==='AUTO_CLASSIFIED')>Auto classé</option><option value="TO_REVIEW" @selected($status==='TO_REVIEW')>À valider</option><option value="UNCLASSIFIED" @selected($status==='UNCLASSIFIED')>Non classé</option><option value="VALIDATED" @selected($status==='VALIDATED')>Validé ingénieur</option></select>
<select name="collection"><option value="">Toutes collections source</option>@foreach($collections as $row)<option value="{{ $row->source_collection }}" @selected($collection===$row->source_collection)>{{ $row->source_collection }} ({{ $row->total }})</option>@endforeach</select>
<button class="btn primary">Rechercher</button>
</form>

<table>
<thead><tr><th>Référence</th><th>Catégorie / titre</th><th>Année</th><th>Classement Civilina</th><th>Confiance</th><th>État fichier</th><th>Provenance</th><th></th></tr></thead>
<tbody>
@forelse($documents as $document)
<tr>
<td><span class="code">{{ $document->reference_code ?: 'À identifier' }}</span><div class="muted" style="font-size:11px">{{ $document->classification_index }}</div></td>
<td><strong>{{ $document->category_label ?: 'Catégorie à déterminer' }}</strong><div>{{ $document->title ?: $document->filename }}</div><div class="muted" style="font-size:11px">{{ $document->subtitle }}</div></td>
<td>{{ trim(($document->publication_month ? $document->publication_month.' ' : '').($document->publication_year ?: '')) ?: '—' }}</td>
<td><strong>{{ $document->discipline ?: '—' }}</strong><div>{{ $document->domain ?: '—' }}</div><div class="muted">{{ $document->subdomain ?: '—' }}</div></td>
<td><span class="badge {{ in_array($document->classification_status,['AUTO_CLASSIFIED','VALIDATED'])?'ok':($document->classification_status==='UNCLASSIFIED'?'bad':'warn') }}">{{ $document->confidence }}% · {{ $document->classification_status }}</span></td>
<td>@if($document->extraction_status==='needs_ocr')<span class="badge warn">NEEDS_OCR</span>@else{{ $document->extraction_status }}@endif @if($document->duplicate_of_id)<span class="badge">Doublon #{{ $document->duplicate_of_id }}</span>@endif</td>
<td>{{ $document->source_collection }}<div class="muted" style="font-size:11px">{{ \Illuminate\Support\Str::limit($document->relative_path,80) }}</div></td>
<td><a class="btn primary" href="{{ route('engineering.normative-librarian.show',['organization'=>$organization,'project'=>$project,'document'=>$document]) }}">Lire & valider</a></td>
</tr>
@empty<tr><td colspan="8">Aucun document avec ces filtres.</td></tr>@endforelse
</tbody>
</table>
</section>
</main>
</body>
</html>
