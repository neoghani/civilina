<!doctype html>
<html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Civilina · Revue des statements atomiques</title>
<style>
body{margin:0;background:#f3f6f8;font-family:Inter,system-ui;color:#102235}main{max-width:1440px;margin:auto;padding:24px 16px 60px}
.head{display:flex;justify-content:space-between;gap:16px;align-items:flex-start}.actions{display:flex;gap:8px;flex-wrap:wrap}.muted{color:#667986}.k{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin:16px 0}.c,.f,.t{background:#fff;border:1px solid #d8e1e7;border-radius:8px}.c{padding:12px}.c strong{font-size:22px;display:block}.f{display:grid;grid-template-columns:160px 220px 1fr auto;gap:8px;padding:10px;margin-bottom:10px}
input,select,button,.b{min-height:38px;border:1px solid #cbd7de;border-radius:6px;background:#fff;padding:7px 9px}.b{display:inline-flex;align-items:center;text-decoration:none;color:#173b51;font-weight:700}.primary{background:#075e73;color:#fff;border-color:#075e73}.t{overflow:auto}table{border-collapse:collapse;width:100%;min-width:1350px}th,td{padding:9px;border-bottom:1px solid #e8edf0;text-align:left;vertical-align:top;font-size:12px}th{font-size:10px;text-transform:uppercase;background:#f8fafb}.statement{max-width:480px;line-height:1.45}.code{font-family:ui-monospace,Consolas,monospace;font-size:10px}.flag{font-size:10px;font-weight:800}.yes{color:#08745e}.no{color:#9b3f3f}
@media(max-width:760px){.head{display:block}.actions{margin-top:12px}.f{grid-template-columns:1fr}.k{grid-template-columns:1fr 1fr}}
</style>
@include('components.civilina-command-theme')
</head><body>
@include('engineering._engineering-nav')
<main>
<div class="head"><div><div class="muted" style="font-size:10px">2K.5 · GATE HUMAIN ATOMIQUE</div><h1>Revue des statements normatifs</h1><p class="muted">L’algorithme classe. Seule une décision humaine documentée peut valider.</p></div>
<div class="actions"><a class="b primary" href="{{ route('engineering.normative-source-review.index',['organization'=>$organization,'project'=>$project]) }}">Revoir les sources</a><a class="b" href="{{ route('engineering.normative-published-registry.index',['organization'=>$organization,'project'=>$project]) }}">Registre publié</a></div></div>
@if(session('success'))<p>{{ session('success') }}</p>@endif
@if($errors->any())<p class="no">{{ $errors->first() }}</p>@endif

<div class="k"><div class="c"><strong>{{ $metrics['candidate'] }}</strong><span class="muted">Candidats</span></div><div class="c"><strong>{{ $metrics['validated'] }}</strong><span class="muted">Validés humainement</span></div><div class="c"><strong>{{ $metrics['rejected'] }}</strong><span class="muted">Rejetés</span></div></div>

<form class="f">
<select name="status">@foreach(['CANDIDATE'=>'Candidat','VALIDATED'=>'Validé','REJECTED'=>'Rejeté','ALL'=>'Tous'] as $value=>$label)<option value="{{ $value }}" @selected($status===$value)>{{ $label }}</option>@endforeach</select>
<select name="type"><option value="">Tous les types</option>@foreach(['REQUIREMENT','TOLERANCE','RECOMMENDATION','PERMISSION','PROCEDURE_CANDIDATE','LIMIT_CANDIDATE','REFERENCE_VALUE','FORMULA_TABLE','OTHER'] as $value)<option value="{{ $value }}" @selected($type===$value)>{{ $value }}</option>@endforeach</select>
<input name="q" value="{{ $q }}" placeholder="Référence, section, statement…"><button>Filtrer</button>
</form>

<div class="t"><table><thead><tr><th>Référence</th><th>Section / contexte</th><th>Type</th><th>Statement atomique</th><th>Valeurs</th><th>Marqueur / confiance</th><th>Autorité / décision</th><th>Revue / Gate</th><th></th></tr></thead><tbody>
@forelse($items as $item)
<tr>
<td><strong>{{ $item->reference_code ?: '—' }}</strong><div class="muted">{{ $item->edition_key ?: 'Édition inconnue' }}</div></td>
<td>{{ $item->section_code ?: '—' }}<div class="muted">{{ $item->section_heading }}</div></td>
<td><strong>{{ $item->statement_type }}</strong></td>
<td class="statement">{{ $item->statement_text }}</td>
<td class="code">{{ str((string)$item->numeric_entities)->limit(110) }}</td>
<td>{{ $item->obligation_marker ?: '—' }}<div class="muted">{{ number_format((float)$item->segmentation_confidence,1) }}%</div></td>
<td>{{ $item->source_authority }}<div class="muted">{{ $item->source_review_status ?: 'UNREVIEWED' }}</div></td>
<td><strong>{{ $item->review_status }}</strong><div class="muted">{{ $item->extraction_state }}</div><div class="flag {{ $item->publication_eligible ? 'yes' : 'no' }}">{{ $item->publication_eligible ? 'ELIGIBLE' : 'NO_PUBLISH' }}</div></td>
<td><a class="b" href="{{ route('engineering.normative-deep-review.show',['organization'=>$organization,'project'=>$project,'candidate'=>$item->id]) }}">Revoir</a></td>
</tr>
@empty<tr><td colspan="9">Aucun statement pour ces filtres.</td></tr>@endforelse
</tbody></table></div><div style="margin-top:12px">{{ $items->links() }}</div>
</main></body></html>
