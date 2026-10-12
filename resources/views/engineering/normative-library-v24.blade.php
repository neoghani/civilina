<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Bibliothèque normative · Civilina</title>
<style>
:root{--navy:#102f46;--ink:#102235;--muted:#66798a;--bg:#f3f6f8;--line:#d9e2e8;--card:#fff;--soft:#edf4f8}
*{box-sizing:border-box}body{margin:0;background:var(--bg);font-family:Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",Arial,sans-serif;color:var(--ink)}
a{color:inherit}.top{height:58px;background:#fff;border-bottom:1px solid var(--line);display:flex;align-items:center;justify-content:space-between;padding:0 24px}
main{padding:24px 28px 60px}.head{display:flex;justify-content:space-between;gap:15px}.eyebrow{font-size:11px;color:var(--muted);letter-spacing:.1em;text-transform:uppercase}
h1{margin:3px 0 5px;font-size:28px}.muted{color:var(--muted)}.btn{display:inline-flex;padding:9px 12px;border:1px solid var(--line);border-radius:8px;background:#fff;text-decoration:none;font-weight:650;font-size:13px}
.btn.primary{background:var(--navy);color:#fff;border-color:var(--navy)}.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin:16px 0}
.stat{background:#fff;border:1px solid var(--line);border-radius:9px;padding:12px}.stat span{display:block;font-size:10px;text-transform:uppercase;color:var(--muted)}.stat strong{font-size:23px}
.panel{background:#fff;border:1px solid var(--line);border-radius:11px;padding:16px;margin-bottom:14px}.filters{display:grid;grid-template-columns:2fr repeat(4,1fr) auto;gap:8px}
input,select{width:100%;padding:9px 10px;border:1px solid var(--line);border-radius:7px;background:#fff}table{width:100%;border-collapse:collapse;font-size:13px;margin-top:12px}
th,td{padding:9px 8px;border-bottom:1px solid #edf0f2;text-align:left;vertical-align:top}th{font-size:10px;text-transform:uppercase;color:var(--muted)}
.code{font-family:ui-monospace,SFMono-Regular,Consolas,monospace;font-weight:700}.badge{display:inline-block;padding:3px 7px;border-radius:999px;background:var(--soft);font-size:11px}
@media(max-width:1100px){.filters{grid-template-columns:1fr 1fr}.stats{grid-template-columns:1fr 1fr}}
</style>
@include('components.civilina-command-theme')
</head>
<body>
@include('engineering._engineering-nav')

<header class="top">
<strong>CIVILINA ENGINEERING · BIBLIOTHÈQUE NORMATIVE</strong>
<div>
<a class="btn" href="{{ route('engineering.normative-v22.index',['organization'=>$organization,'project'=>$project]) }}">Agent normatif</a>
<a class="btn" href="{{ route('engineering.workspace.bridge',['organization'=>$organization,'project'=>$project]) }}">Workspace</a>
</div>
</header>
<main>
<div class="head">
<div><div class="eyebrow">Normes organisées par métier</div><h1>Bibliothèque normative</h1>
<div class="muted">Discipline → domaine → sous-domaine → famille → norme → édition → sections → exigences.</div></div>
</div>

<div class="stats">
<div class="stat"><span>Normes</span><strong>{{ $counts['standards'] }}</strong></div>
<div class="stat"><span>Classées</span><strong>{{ $counts['classified'] }}</strong></div>
<div class="stat"><span>À revoir</span><strong>{{ $counts['review'] }}</strong></div>
<div class="stat"><span>Liens QA/QC</span><strong>{{ $counts['links'] }}</strong></div>
</div>

<section class="panel">
<h2>Recherche</h2>
<form method="get" class="filters">
<input name="q" value="{{ $q }}" placeholder="Référence, titre, famille : EN 206, ISO 9606, GTR...">
<select name="discipline"><option value="">Toutes disciplines</option>@foreach($disciplines as $row)<option value="{{ $row->discipline }}" @selected($discipline===$row->discipline)>{{ $taxonomy->disciplineLabel($row->discipline) }} ({{ $row->total }})</option>@endforeach</select>
<select name="domain"><option value="">Tous domaines</option>@foreach($domains as $row)<option value="{{ $row->domain }}" @selected($domain===$row->domain)>{{ $taxonomy->domainLabel($row->domain) }} ({{ $row->total }})</option>@endforeach</select>
<select name="subdomain"><option value="">Tous sous-domaines</option>@foreach($subdomains as $row)<option value="{{ $row->subdomain }}" @selected($subdomain===$row->subdomain)>{{ $row->subdomain }} ({{ $row->total }})</option>@endforeach</select>
<select name="collection"><option value="">Toutes collections source</option>@foreach($collections as $row)<option value="{{ $row->source_collection }}" @selected($collection===$row->source_collection)>{{ $row->source_collection }} ({{ $row->total }})</option>@endforeach</select>
<button class="btn primary" type="submit">Rechercher</button>
</form>

<table>
<thead><tr><th>Référence</th><th>Titre</th><th>Discipline</th><th>Domaine</th><th>Sous-domaine</th><th>Famille</th><th>Collection source</th><th>Sources</th><th></th></tr></thead>
<tbody>
@forelse($standards as $standard)
<tr>
<td class="code">{{ $standard->canonical_code ?: 'À identifier' }}</td>
<td>{{ $standard->title ?: 'Titre à confirmer' }}</td>
<td><span class="badge">{{ $taxonomy->disciplineLabel($standard->discipline) }}</span></td>
<td><span class="badge">{{ $taxonomy->domainLabel($standard->domain) }}</span></td>
<td>{{ $standard->subdomain ?: '—' }}</td>
<td>{{ $standard->family_name ?: '—' }}</td>
<td>{{ $standard->source_collection ?: '—' }}</td>
<td>{{ $standard->sources_count }}</td>
<td><a class="btn primary" href="{{ route('engineering.normative-library.show',['organization'=>$organization,'project'=>$project,'standard'=>$standard]) }}">Consulter</a></td>
</tr>
@empty<tr><td colspan="9">Aucune norme avec ces filtres.</td></tr>@endforelse
</tbody>
</table>
</section>
</main>
</body>
</html>
