<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{ $document->reference_code ?: $document->filename }} · Civilina</title>
<style>
:root{--navy:#102f46;--ink:#102235;--muted:#65798a;--bg:#f3f6f8;--line:#d8e1e7}
*{box-sizing:border-box}body{margin:0;background:var(--bg);font-family:Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",Arial,sans-serif;color:var(--ink)}
.top{height:58px;background:#fff;border-bottom:1px solid var(--line);display:flex;align-items:center;justify-content:space-between;padding:0 22px}.btn{display:inline-flex;padding:8px 11px;border:1px solid var(--line);border-radius:7px;background:#fff;text-decoration:none;color:inherit;font-weight:650;font-size:12px;cursor:pointer}.btn.primary{background:var(--navy);color:#fff;border-color:var(--navy)}
.layout{display:grid;grid-template-columns:minmax(600px,1.35fr) minmax(430px,.85fr);height:calc(100vh - 58px)}.viewer{background:#e8edf1;border-right:1px solid var(--line);display:flex;flex-direction:column;min-width:0}.viewerbar{padding:10px 12px;background:#fff;border-bottom:1px solid var(--line)}.frame{width:100%;height:100%;border:0;background:#fff}
.details{padding:16px;overflow:auto}.card{background:#fff;border:1px solid var(--line);border-radius:10px;padding:14px;margin-bottom:12px}.card h2{margin:0 0 10px;font-size:17px}.muted{color:var(--muted)}.grid2{display:grid;grid-template-columns:1fr 1fr;gap:8px}label{display:block;font-size:11px;color:var(--muted);margin:7px 0 4px}input,textarea{width:100%;padding:8px 9px;border:1px solid var(--line);border-radius:7px;background:#fff;font:inherit}textarea{min-height:85px}.code{font-family:ui-monospace,SFMono-Regular,Consolas,monospace;font-weight:700}.message{padding:10px 12px;background:#eaf5ff;border:1px solid #bddcf5;border-radius:8px;margin-bottom:12px}
@media(max-width:1100px){.layout{grid-template-columns:1fr;height:auto}.viewer{height:70vh}.details{overflow:visible}}
</style>
@include('components.civilina-command-theme')
</head>
<body>
@include('engineering._engineering-nav')

<header class="top">
<div><strong class="code">{{ $document->reference_code ?: 'Référence à identifier' }}</strong> · {{ $document->title ?: $document->filename }}</div>
<a class="btn" href="{{ route('engineering.normative-librarian.index',['organization'=>$organization,'project'=>$project]) }}">← Catalogue</a>
</header>

<div class="layout">
<section class="viewer">
<div class="viewerbar"><strong>Document original</strong><div class="muted" style="font-size:11px">{{ $document->relative_path }}</div></div>
@if(in_array(strtolower((string)$document->extension),['pdf','txt','md']))
<iframe class="frame" src="{{ route('engineering.normative-librarian.inline',['organization'=>$organization,'project'=>$project,'document'=>$document]) }}"></iframe>
@else
<div style="padding:24px"><strong>Aperçu navigateur non disponible pour {{ strtoupper($document->extension) }}.</strong><p>Les métadonnées extraites restent modifiables à droite.</p></div>
@endif
</section>

<aside class="details">
@if(session('normative_message'))<div class="message">{{ session('normative_message') }}</div>@endif

<section class="card">
<h2>Fiche extraite</h2>
<div class="muted">Confiance agent : {{ $document->confidence }}% · {{ $document->classification_status }} · extraction : {{ $document->extraction_status }}</div>
<form method="post" action="{{ route('engineering.normative-librarian.update',['organization'=>$organization,'project'=>$project,'document'=>$document]) }}">
@csrf @method('PATCH')
<div class="grid2">
<div><label>Référence normative</label><input name="reference_code" value="{{ $document->reference_code }}"></div>
<div><label>Organisme</label><input name="issuer" value="{{ $document->issuer }}"></div>
<div><label>Catégorie éditoriale</label><input name="category_label" value="{{ $document->category_label }}"></div>
<div><label>Indice de classement</label><input name="classification_index" value="{{ $document->classification_index }}"></div>
<div style="grid-column:1/-1"><label>Titre</label><input name="title" value="{{ $document->title }}"></div>
<div style="grid-column:1/-1"><label>Sous-titre</label><input name="subtitle" value="{{ $document->subtitle }}"></div>
<div><label>Mois</label><input name="publication_month" value="{{ $document->publication_month }}"></div>
<div><label>Année</label><input name="publication_year" value="{{ $document->publication_year }}"></div>
<div><label>Discipline</label><input name="discipline" value="{{ $document->discipline }}"></div>
<div><label>Domaine</label><input name="domain" value="{{ $document->domain }}"></div>
<div style="grid-column:1/-1"><label>Sous-domaine</label><input name="subdomain" value="{{ $document->subdomain }}"></div>
<div style="grid-column:1/-1"><label>ICS (séparés par ;)</label><input name="ics_text" value="{{ implode('; ',(array)$document->ics_codes) }}"></div>
<div style="grid-column:1/-1"><label>Notes de validation</label><textarea name="review_notes">{{ $document->review_notes }}</textarea></div>
</div>
<button class="btn primary" style="margin-top:10px">Valider comme vérité Civilina</button>
</form>
</section>

<section class="card">
<h2>Provenance</h2>
<div><strong>Collection source :</strong> {{ $document->source_collection }}</div>
<div><strong>Fichier :</strong> {{ $document->filename }}</div>
<div><strong>SHA-256 :</strong> <span class="code" style="font-size:11px">{{ $document->sha256 }}</span></div>
@if($document->duplicate_of_id)<div><strong>Doublon de :</strong> #{{ $document->duplicate_of_id }}</div>@endif
<div><strong>Publication registre :</strong> {{ $document->publish_status }} @if($document->registry_standard_id) · standard #{{ $document->registry_standard_id }} @endif</div>
</section>

<section class="card">
<h2>Texte de couverture lu par l’agent</h2>
<pre style="white-space:pre-wrap;font:12px/1.45 ui-monospace,SFMono-Regular,Consolas,monospace;max-height:450px;overflow:auto">{{ $document->cover_text }}</pre>
</section>
</aside>
</div>
</body>
</html>
