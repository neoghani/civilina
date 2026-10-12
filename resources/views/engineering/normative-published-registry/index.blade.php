<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Civilina · Registre normatif publié</title>
<style>
body{margin:0;background:#f3f6f8;font-family:Inter,system-ui;color:#102235}
main{max-width:1280px;margin:auto;padding:24px 16px 60px}
.muted{color:#70808b}.k{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin:16px 0}
.c,.f,.t{background:#fff;border:1px solid #d8e1e7;border-radius:10px}
.c{padding:12px}.c strong{display:block;font-size:22px}
.f{display:grid;grid-template-columns:160px 180px 1fr auto;gap:8px;padding:10px;margin-bottom:10px}
input,select,button{min-height:38px;border:1px solid #cbd7de;border-radius:8px;background:#fff;padding:7px 9px}
.t{overflow:auto}table{border-collapse:collapse;width:100%;min-width:1050px}
th,td{padding:9px;border-bottom:1px solid #e8edf0;text-align:left;vertical-align:top;font-size:12px}
th{font-size:10px;text-transform:uppercase;background:#f8fafb}
.hash{font-family:ui-monospace,Consolas,monospace;font-size:9px;color:#70808b}
@media(max-width:700px){.k{grid-template-columns:1fr 1fr}.f{grid-template-columns:1fr}}
</style>
@include('components.civilina-command-theme')
</head>
<body>
@include('engineering._engineering-nav')
<main>
<div class="muted" style="font-size:10px">BLOCK 2K.5.2 · REGISTRE PUBLIÉ</div>
<h1>Exigences normatives publiées</h1>
<p class="muted">Seuls les statements atomiques VALIDATED franchissant tous les Gates peuvent entrer dans ce registre. Chaque révision conserve sa provenance et son empreinte.</p>

<div class="k">
<div class="c"><strong>{{ $metrics['active'] }}</strong><span class="muted">Actifs</span></div>
<div class="c"><strong>{{ $metrics['revoked'] }}</strong><span class="muted">Révoqués</span></div>
<div class="c"><strong>{{ $metrics['requirements'] }}</strong><span class="muted">Exigences</span></div>
<div class="c"><strong>{{ $metrics['tolerances'] }}</strong><span class="muted">Tolérances</span></div>
</div>

<form class="f">
<select name="state">
<option value="ACTIVE" @selected($state==='ACTIVE')>Actifs</option>
<option value="REVOKED" @selected($state==='REVOKED')>Révoqués</option>
<option value="ALL" @selected($state==='ALL')>Tous</option>
</select>
<select name="type">
<option value="">Tous types</option>
<option value="REQUIREMENT" @selected($type==='REQUIREMENT')>Exigence</option>
<option value="TOLERANCE" @selected($type==='TOLERANCE')>Tolérance</option>
</select>
<input name="q" value="{{ $q }}" placeholder="Référence, section, texte...">
<button>Filtrer</button>
</form>

<div class="t">
<table>
<thead><tr>
<th>Référence</th><th>Section</th><th>Type</th><th>Texte validé</th>
<th>Valeur</th><th>Révision</th><th>Validation humaine</th><th>Provenance</th>
</tr></thead>
<tbody>
@forelse($items as $i)
<tr>
<td><strong>{{ $i->reference_code }}</strong><div class="muted">{{ $i->edition_key }}</div></td>
<td><strong>{{ $i->section_code ?: '—' }}</strong><div class="muted">{{ $i->heading }}</div></td>
<td>{{ $i->requirement_type }}</td>
<td style="max-width:520px">{{ $i->requirement_text }}</td>
<td>{{ $i->operator }} {{ $i->numeric_value }} {{ $i->unit }}</td>
<td>R{{ $i->revision }}<div class="muted">{{ $i->publication_state }}</div></td>
<td>{{ $i->human_validated_at }}<div class="muted">{{ $i->human_validation_note }}</div></td>
<td>
<div>{{ $i->source_atomic_statement_id ? 'Statement #'.$i->source_atomic_statement_id : 'Archive legacy #'.$i->source_candidate_id }}</div>
<div class="hash">{{ substr($i->provenance_hash,0,18) }}…</div>
</td>
</tr>
@empty
<tr><td colspan="8">Aucune exigence publiée.</td></tr>
@endforelse
</tbody>
</table>
</div>

<div style="margin-top:12px">{{ $items->links() }}</div>
</main>
</body>
</html>
