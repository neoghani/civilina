<!doctype html>
<html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Civilina · Décision atomique</title>
<style>
body{margin:0;background:#f3f6f8;font-family:Inter,system-ui;color:#102235}main{max-width:1120px;margin:auto;padding:24px 16px 60px}.g{display:grid;grid-template-columns:2fr 1fr;gap:12px}.p{background:#fff;border:1px solid #d8e1e7;border-radius:8px;padding:16px}.muted{color:#667986}.statement{font-size:17px;line-height:1.55;border-left:4px solid #08758a;background:#f5fafb;padding:14px}.context{white-space:pre-wrap;max-height:430px;overflow:auto;background:#f8fafb;padding:12px;border-radius:6px;font-size:12px;line-height:1.45}.btn{width:100%;min-height:40px;border-radius:6px;border:1px solid #cbd7de;font-weight:800;background:#fff;margin-top:8px}.ok{background:#075e73;color:white}.bad{color:#963f3f;border-color:#d9bcbc}textarea{width:100%;min-height:95px;border:1px solid #cbd7de;border-radius:6px;padding:8px;box-sizing:border-box}.back{display:inline-block;margin-bottom:12px}.meta{display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:12px}.gate{padding:10px;border:1px solid #d8e1e7;border-radius:6px}.history{font-size:11px;border-top:1px solid #e3e9ed;padding:8px 0}.error{color:#963f3f}.code{font-family:ui-monospace,Consolas,monospace;word-break:break-all;font-size:10px}
@media(max-width:760px){.g{grid-template-columns:1fr}.meta{grid-template-columns:1fr}}
</style>
@include('components.civilina-command-theme')
</head><body>@include('engineering._engineering-nav')<main>
<a class="back" href="{{ route('engineering.normative-deep-review.index',['organization'=>$organization,'project'=>$project]) }}">← Retour aux statements</a>
<h1>{{ $item->reference_code ?: 'Référence non déterminée' }}</h1><p class="muted">{{ $item->edition_key ?: 'Édition inconnue' }} · Section {{ $item->section_code ?: '—' }} · {{ $item->section_heading }}</p>
@if(session('success'))<p>{{ session('success') }}</p>@endif @if($errors->any())<p class="error">{{ $errors->first() }}</p>@endif
<div class="g"><div class="p">
<div class="meta"><p><strong>Type</strong><br>{{ $item->statement_type }}</p><p><strong>Confiance</strong><br>{{ number_format((float)$item->segmentation_confidence,1) }}%</p><p><strong>Marqueur</strong><br>{{ $item->obligation_marker ?: '—' }}</p><p><strong>Valeurs détectées</strong><br><span class="code">{{ $item->numeric_entities ?: '—' }}</span></p></div>
<div class="statement">{{ $item->statement_text }}</div>
<h3>Contexte de section</h3><div class="context">{{ $item->section_text }}</div>
<h3>Provenance</h3><p class="code">source_hash: {{ $item->source_hash }}<br>provenance_hash: {{ $item->provenance_hash ?: 'absent avant validation' }}</p>
</div><aside class="p">
<div class="gate"><strong>{{ $item->gate['eligible'] ? 'ELIGIBLE' : 'NO_PUBLISH' }}</strong><p class="muted">{{ $item->gate['code'] }} · {{ $item->gate['reason'] }}</p></div>
<p><strong>Source détectée :</strong><br>{{ $item->source_authority }}</p><p><strong>Décision de source :</strong><br>{{ $item->source_review_status ?: 'UNREVIEWED' }}</p><p><strong>Statut humain :</strong><br>{{ $item->review_status }}</p><p class="muted" style="font-size:11px">La confiance algorithmique ne constitue jamais une validation humaine.</p>
<form method="post" action="{{ route('engineering.normative-deep-review.decision',['organization'=>$organization,'project'=>$project,'candidate'=>$item->id]) }}">@csrf<input type="hidden" name="decision" value="VALIDATED"><textarea name="note" placeholder="Note de validation facultative">{{ $item->review_status==='VALIDATED' ? $item->review_note : '' }}</textarea><button class="btn ok">Valider humainement</button></form>
<form method="post" action="{{ route('engineering.normative-deep-review.decision',['organization'=>$organization,'project'=>$project,'candidate'=>$item->id]) }}" style="margin-top:14px">@csrf<input type="hidden" name="decision" value="REJECTED"><textarea name="note" required placeholder="Motif obligatoire du rejet">{{ $item->review_status==='REJECTED' ? $item->review_note : '' }}</textarea><button class="btn bad">Rejeter</button></form>
@if($item->review_status!=='CANDIDATE')<form method="post" action="{{ route('engineering.normative-deep-review.decision',['organization'=>$organization,'project'=>$project,'candidate'=>$item->id]) }}">@csrf<input type="hidden" name="decision" value="CANDIDATE"><button class="btn">Remettre en revue</button></form>@endif
<h3>Historique</h3>@forelse($history as $event)<div class="history"><strong>{{ $event->decision }}</strong> · {{ $event->decided_at }}<br>{{ $event->actor_name ?: 'Utilisateur authentifié' }}<br><span class="muted">{{ $event->note }}</span></div>@empty<p class="muted">Aucune décision enregistrée.</p>@endforelse
</aside></div></main></body></html>
