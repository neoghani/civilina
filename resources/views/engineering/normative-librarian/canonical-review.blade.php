<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Revue canonique · Civilina</title>
<style>
:root{--navy:#102f46;--ink:#102235;--muted:#687b8c;--bg:#f3f6f8;--line:#d8e1e7;--ok:#167458;--warn:#a96400;--bad:#b42318}
*{box-sizing:border-box}body{margin:0;background:var(--bg);font-family:Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",Arial,sans-serif;color:var(--ink)}
a{color:inherit}.top{height:58px;background:#fff;border-bottom:1px solid var(--line);display:flex;align-items:center;justify-content:space-between;padding:0 24px}
main{padding:24px 28px 60px}.btn{display:inline-flex;padding:8px 11px;border:1px solid var(--line);border-radius:8px;background:#fff;text-decoration:none;font-weight:650;font-size:12px;cursor:pointer}.btn.primary{background:var(--navy);color:#fff;border-color:var(--navy)}
.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin:16px 0}.stat{display:block;padding:13px;background:#fff;border:1px solid var(--line);border-radius:9px;text-decoration:none}.stat span{display:block;color:var(--muted);font-size:10px;text-transform:uppercase}.stat strong{font-size:24px}
.panel{background:#fff;border:1px solid var(--line);border-radius:10px;padding:15px;margin-top:14px}table{width:100%;border-collapse:collapse;font-size:13px}th,td{padding:9px;border-bottom:1px solid #edf0f2;text-align:left;vertical-align:top}th{font-size:10px;text-transform:uppercase;color:var(--muted)}
input{width:100%;padding:7px;border:1px solid var(--line);border-radius:6px}.filters{display:grid;grid-template-columns:2fr 1fr auto;gap:8px;margin-bottom:12px}.badge{display:inline-block;padding:3px 7px;border-radius:999px;background:#eef3f6;font-size:11px}.ok{color:var(--ok)}.warn{color:var(--warn)}.bad{color:var(--bad)}.message{padding:10px 12px;background:#eaf5ff;border:1px solid #bddcf5;border-radius:8px;margin:12px 0}
</style>
@include('components.civilina-command-theme')
</head>
<body>
@include('engineering._engineering-nav')

<header class="top">
<strong>CIVILINA · REVUE CANONIQUE</strong>
<a class="btn" href="{{ route('engineering.normative-librarian.index',['organization'=>$organization,'project'=>$project]) }}">← Bibliothèque locale</a>
</header>

<main>
<h1 style="margin-bottom:5px">Références et éditions canoniques</h1>
<div style="color:var(--muted)">Une validation ici s’applique à toutes les copies de la même référence et de la même édition.</div>

@if(session('normative_message'))<div class="message">{{ session('normative_message') }}</div>@endif

<div class="stats">
<a class="stat" href="?status=SAFE"><span>SAFE</span><strong>{{ $counts['safe'] }}</strong></a>
<a class="stat" href="?status=CONFLICT"><span>Conflits</span><strong>{{ $counts['conflict'] }}</strong></a>
<a class="stat" href="?status=INSUFFICIENT"><span>Consensus insuffisant</span><strong>{{ $counts['insufficient'] }}</strong></a>
<a class="stat" href=""><span>Validés ingénieur</span><strong>{{ $counts['validated'] }}</strong></a>
</div>

<section class="panel">
<form method="get" class="filters">
<input name="q" value="{{ $q }}" placeholder="Référence, titre, domaine...">
<select name="status" style="padding:7px;border:1px solid var(--line);border-radius:6px">
<option value="">Tous</option>
<option value="SAFE" @selected($status==='SAFE')>SAFE</option>
<option value="CONFLICT" @selected($status==='CONFLICT')>Conflits</option>
<option value="INSUFFICIENT" @selected($status==='INSUFFICIENT')>Insuffisants</option>
<option value="STRONG_REFERENCE" @selected($status==='STRONG_REFERENCE')>Référence forte</option>
<option value="CONSENSUS_SAFE" @selected($status==='CONSENSUS_SAFE')>Consensus sûr</option>
</select>
<button class="btn primary">Filtrer</button>
</form>

<table>
<thead><tr><th>Référence / édition</th><th>Titre</th><th>Classement</th><th>Consensus</th><th>Validation groupe</th></tr></thead>
<tbody>
@forelse($groups as $document)
<tr>
<td><strong>{{ $document->reference_code ?: 'Sans référence' }}</strong><div>{{ $document->publication_year ?: 'année inconnue' }}</div></td>
<td>{{ $document->title ?: $document->filename }}</td>
<td><strong>{{ $document->discipline ?: '—' }}</strong><div>{{ $document->domain ?: '—' }}</div><div>{{ $document->subdomain ?: '—' }}</div></td>
<td>
<span class="badge {{ $document->consensus_status==='CONFLICT'?'bad':($document->audit_status==='SAFE'?'ok':'warn') }}">{{ $document->consensus_status ?: '—' }}</span>
<div style="font-size:11px;color:var(--muted);max-width:420px">{{ $document->consensus_reason }}</div>
</td>
<td style="min-width:290px">
<form method="post" action="{{ route('engineering.normative-canonical-review.validate',['organization'=>$organization,'project'=>$project,'document'=>$document]) }}">
@csrf @method('PATCH')
<div style="display:grid;grid-template-columns:1fr;gap:5px">
<input name="discipline" value="{{ $document->discipline }}" placeholder="Discipline" required>
<input name="domain" value="{{ $document->domain }}" placeholder="Domaine" required>
<input name="subdomain" value="{{ $document->subdomain }}" placeholder="Sous-domaine">
<button class="btn primary">Valider la référence/édition</button>
</div>
</form>
</td>
</tr>
@empty<tr><td colspan="5">Aucun groupe avec ces filtres.</td></tr>@endforelse
</tbody>
</table>
</section>
</main>
</body>
</html>
