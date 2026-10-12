<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{ $standard->canonical_code }} · Civilina</title>
<style>
:root{--navy:#102f46;--ink:#102235;--muted:#65798a;--bg:#f3f6f8;--line:#d9e2e8;--card:#fff;--soft:#edf4f8;--ok:#167458}
*{box-sizing:border-box}body{margin:0;background:var(--bg);font-family:Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",Arial,sans-serif;color:var(--ink)}
a{color:inherit}.top{height:58px;background:#fff;border-bottom:1px solid var(--line);display:flex;align-items:center;justify-content:space-between;padding:0 22px}
.layout{display:grid;grid-template-columns:minmax(560px,1.35fr) minmax(420px,.85fr);height:calc(100vh - 58px)}.viewer{background:#e8edf1;border-right:1px solid var(--line);display:flex;flex-direction:column;min-width:0}
.viewerbar{background:#fff;border-bottom:1px solid var(--line);padding:10px 12px;display:flex;gap:8px;align-items:center}.viewerframe{width:100%;height:100%;border:0;background:#fff}
.details{overflow:auto;padding:16px;background:var(--bg)}.card{background:#fff;border:1px solid var(--line);border-radius:10px;padding:14px;margin-bottom:12px}.card h2{margin:0 0 10px;font-size:17px}.muted{color:var(--muted)}.code{font-family:ui-monospace,SFMono-Regular,Consolas,monospace;font-weight:700}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:8px}label{font-size:11px;color:var(--muted);display:block;margin:7px 0 4px}input,select,textarea{width:100%;padding:8px 9px;border:1px solid var(--line);border-radius:7px;background:#fff;font:inherit}
textarea{min-height:90px}.btn{display:inline-flex;padding:8px 11px;border:1px solid var(--line);border-radius:7px;background:#fff;text-decoration:none;font-weight:650;font-size:12px;cursor:pointer}.btn.primary{background:var(--navy);color:#fff;border-color:var(--navy)}
.req{border-left:3px solid #1769aa;background:#fafcff;padding:10px;margin:8px 0}.pill{display:inline-block;padding:3px 7px;border-radius:999px;background:var(--soft);font-size:11px}.pill.ok{background:#e5f4ee;color:var(--ok)}
@media(max-width:1100px){.layout{grid-template-columns:1fr;height:auto}.viewer{height:70vh}.details{overflow:visible}}
</style>
@include('components.civilina-command-theme')
</head>
<body>
@include('engineering._engineering-nav')

<header class="top">
<div><strong class="code">{{ $standard->canonical_code ?: 'Référence à identifier' }}</strong> · {{ $standard->title }}</div>
<div><a class="btn" href="{{ route('engineering.normative-library.index',['organization'=>$organization,'project'=>$project]) }}">← Bibliothèque</a></div>
</header>

<div class="layout">
<section class="viewer">
<div class="viewerbar">
<strong>Document source</strong>
@if($standard->sources->count() > 1)
<select onchange="location.href=this.value" style="max-width:520px">
@foreach($standard->sources as $source)
<option value="{{ route('engineering.normative-library.show',['organization'=>$organization,'project'=>$project,'standard'=>$standard,'source'=>$source->id]) }}" @selected($selectedSource?->id===$source->id)>{{ $source->relative_path }}</option>
@endforeach
</select>
@else
<span class="muted">{{ $selectedSource?->relative_path ?: 'Aucune source' }}</span>
@endif
</div>

@if($selectedSource && in_array(strtolower(pathinfo($selectedSource->relative_path,PATHINFO_EXTENSION)),['pdf','txt','md','png','jpg','jpeg','webp']))
<iframe class="viewerframe" src="{{ route('engineering.normative-library.inline',['organization'=>$organization,'project'=>$project,'standard'=>$standard,'source'=>$selectedSource]) }}"></iframe>
@else
<div style="padding:24px"><strong>Aperçu navigateur indisponible pour ce format.</strong><p class="muted">Les sections/exigences déjà extraites restent consultables à droite.</p></div>
@endif
</section>

<aside class="details">
@if(session('normative_message'))<div class="card">{{ session('normative_message') }}</div>@endif

<section class="card">
<h2>Fiche normative</h2>
<form method="post" action="{{ route('engineering.normative-library.standard.update',['organization'=>$organization,'project'=>$project,'standard'=>$standard]) }}">
@csrf @method('PATCH')
<div class="grid2">
<div><label>Titre</label><input name="title" value="{{ $standard->title }}"></div>
<div><label>Organisme</label><input name="issuing_body" value="{{ $standard->issuing_body }}"></div>
<div><label>Discipline</label><input name="discipline" value="{{ $standard->discipline }}"></div>
<div><label>Domaine</label><input name="domain" value="{{ $standard->domain }}"></div>
<div><label>Sous-domaine</label><input name="subdomain" value="{{ $standard->subdomain }}"></div>
<div><label>Famille</label><input name="family_name" value="{{ $standard->family_name }}"></div>
<div style="grid-column:1/-1"><label>Collection source</label><input name="source_collection" value="{{ $standard->source_collection }}"></div>
</div>
<div style="margin-top:9px"><button class="btn primary">Enregistrer la fiche</button></div>
</form>
</section>

<section class="card">
<h2>Éditions · sections · exigences</h2>
@forelse($standard->editions as $edition)
<div style="margin:12px 0"><strong>Édition {{ $edition->edition_label ?? $edition->publication_year ?? '—' }}</strong></div>
@forelse($edition->sections as $section)
<div class="req">
<strong>Section {{ $section->section_code ?? '—' }} {{ $section->heading ? '— '.$section->heading : '' }}</strong>
<div class="muted" style="font-size:12px;margin:4px 0">Page {{ $section->page_number ?? '—' }}</div>
@foreach($section->requirements as $requirement)
<form method="post" action="{{ route('engineering.normative-library.requirement.update',['organization'=>$organization,'project'=>$project,'requirement'=>$requirement]) }}" style="border-top:1px solid #e8edf1;padding-top:8px;margin-top:8px">
@csrf @method('PATCH')
<div class="grid2">
<div><label>Type</label><input name="requirement_type" value="{{ $requirement->requirement_type }}"></div>
<div><label>Sujet</label><input name="subject" value="{{ $requirement->subject }}"></div>
<div style="grid-column:1/-1"><label>Texte exigence / tolérance</label><textarea name="requirement_text">{{ $requirement->requirement_text }}</textarea></div>
<div><label>Opérateur</label><input name="operator" value="{{ $requirement->operator }}"></div>
<div><label>Unité</label><input name="unit" value="{{ $requirement->unit }}"></div>
<div><label>Minimum</label><input name="min_value" value="{{ $requirement->min_value }}"></div>
<div><label>Maximum</label><input name="max_value" value="{{ $requirement->max_value }}"></div>
<div><label>Nominal</label><input name="nominal_value" value="{{ $requirement->nominal_value }}"></div>
<div><label>Tolérance +</label><input name="tolerance_plus" value="{{ $requirement->tolerance_plus }}"></div>
<div><label>Tolérance -</label><input name="tolerance_minus" value="{{ $requirement->tolerance_minus }}"></div>
</div>
<button class="btn" style="margin-top:8px">Corriger cette exigence</button>
</form>
@endforeach
</div>
@empty<div class="muted">Aucune section extraite pour cette édition.</div>@endforelse
@empty<div class="muted">Aucune édition enregistrée.</div>@endforelse
</section>

<section class="card">
<h2>Liaisons QA/QC existantes</h2>
@forelse($links as $link)
<div class="req">
<span class="pill {{ $link->status==='validated'?'ok':'' }}">{{ $link->status }}</span>
<strong>Score {{ $link->score }}%</strong>
<div>{{ $link->requirement?->requirement_text }}</div>
</div>
@empty<div class="muted">Aucune liaison validée/proposée pour cette norme dans ce projet.</div>@endforelse
</section>
</aside>
</div>
</body>
</html>
