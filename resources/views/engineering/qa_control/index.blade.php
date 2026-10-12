@extends('layouts.app')

@section('content')
<div style="max-width:1500px;margin:0 auto;padding:24px;font-family:Arial,sans-serif">
    <div style="display:flex;justify-content:space-between;gap:16px;align-items:flex-start;margin-bottom:20px">
        <div>
            <div style="font-size:12px;text-transform:uppercase;letter-spacing:.08em;color:#64748b">Civilina Engineering · QA/QC</div>
            <h1 style="margin:5px 0 6px;font-size:28px">Inspections · Essais · PV · Registres</h1>
            <div style="color:#64748b">Projet : {{ $project->name ?? $project->title ?? $project->getKey() }}</div>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <a href="{{ route('engineering.itp.index', [$organization->getKey(), $project->getKey()]) }}" style="padding:10px 14px;border:1px solid #cbd5e1;border-radius:7px;text-decoration:none;color:#0f172a">ITP</a>
            <a href="{{ route('engineering.workspace.bridge',['organization'=>$organization,'project'=>$project]) }}" style="padding:10px 14px;border:1px solid #cbd5e1;border-radius:7px;text-decoration:none;color:#0f172a">Workspace</a>
        </div>
    </div>

    @if(session('success'))
        <div style="padding:12px 14px;border:1px solid #86efac;background:#f0fdf4;border-radius:8px;margin-bottom:16px">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div style="padding:12px 14px;border:1px solid #fca5a5;background:#fef2f2;border-radius:8px;margin-bottom:16px">
            <strong>À corriger :</strong>
            <ul style="margin:8px 0 0 18px">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <section style="border:1px solid #e2e8f0;border-radius:10px;padding:18px;margin-bottom:22px;background:#fff">
        <h2 style="margin:0 0 6px;font-size:19px">Générer la matrice QA/QC selon le type de travaux</h2>
        <p style="margin:0 0 16px;color:#64748b">Civilina génère les enregistrements attendus. Les valeurs d’acceptation restent à renseigner depuis le CCTP, Design Basis, plans, procédures ou normes applicables au projet.</p>
        <form method="POST" action="{{ route('engineering.qa-control.generate', [$organization->getKey(), $project->getKey()]) }}" style="display:grid;grid-template-columns:repeat(5,minmax(150px,1fr));gap:10px;align-items:end">
            @csrf
            <label>Type de travaux<br><select name="work_type" required style="width:100%;padding:9px"><option value="">— Choisir —</option>@foreach($workTypes as $key=>$label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></label>
            <label>Portée<br><select name="scope_type" style="width:100%;padding:9px"><option value="project">Projet</option><option value="work_package">Work Package</option><option value="activity">Activity</option><option value="zone">Zone</option><option value="ouvrage">Ouvrage</option></select></label>
            <label>Référence portée<br><input name="scope_ref" placeholder="WP-STR-001 / ACT-..." style="width:100%;padding:9px"></label>
            <label>Libellé portée<br><input name="scope_label" required placeholder="Structure béton — Hangar 01" style="width:100%;padding:9px"></label>
            <label>ITP associé<br><select name="engineering_itp_id" style="width:100%;padding:9px"><option value="">Aucun</option>@foreach($itps as $itp)<option value="{{ $itp->getKey() }}">{{ $itp->code }} — {{ $itp->title }}</option>@endforeach</select></label>
            <button style="grid-column:1/-1;padding:11px;border:0;border-radius:7px;background:#0f172a;color:#fff;font-weight:700;cursor:pointer">Générer contrôles / essais / handover</button>
        </form>
    </section>

    <div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;align-items:start">
        <main>
            <h2 style="font-size:20px;margin:0 0 12px">Contrôles & essais ({{ $inspections->count() }})</h2>
            @forelse($inspections as $inspection)
                @php($schema = $inspection->register_type ? ($registerSchemas[$inspection->register_type] ?? null) : null)
                <article style="border:1px solid #e2e8f0;border-radius:10px;margin-bottom:16px;background:#fff;overflow:hidden">
                    <div style="padding:14px 16px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;gap:10px">
                        <div>
                            <strong>{{ $inspection->inspection_code }}</strong> — {{ $inspection->title }}
                            <div style="font-size:12px;color:#64748b;margin-top:4px">{{ $workTypes[$inspection->work_type] ?? $inspection->work_type }} · {{ $inspection->scope_label }} · {{ $inspection->checkpoint_type ?: '—' }}</div>
                        </div>
                        <div style="font-size:12px;text-align:right">Statut : <strong>{{ $inspection->status }}</strong><br>Autorisation : <strong>{{ $inspection->authorization_status }}</strong></div>
                    </div>

                    <div style="padding:14px 16px">
                        <form method="POST" action="{{ route('engineering.qa-control.inspections.update', [$organization->getKey(), $project->getKey(), $inspection->getKey()]) }}">
                            @csrf
                            <div style="display:grid;grid-template-columns:repeat(4,minmax(140px,1fr));gap:9px">
                                <label>Statut<br><select name="status" style="width:100%;padding:8px">@foreach(['planned'=>'Planifié','requested'=>'Demandé','conforming'=>'Conforme','nonconforming'=>'Non conforme','conditional'=>'Conditionnel','cancelled'=>'Annulé'] as $k=>$v)<option value="{{ $k }}" @selected($inspection->status===$k)>{{ $v }}</option>@endforeach</select></label>
                                <label>Autorisation client<br><select name="authorization_status" style="width:100%;padding:8px">@foreach(['none'=>'Non requise','requested'=>'Demandée','granted'=>'Accordée','rejected'=>'Refusée'] as $k=>$v)<option value="{{ $k }}" @selected($inspection->authorization_status===$k)>{{ $v }}</option>@endforeach</select></label>
                                <label>Date inspection<br><input type="datetime-local" name="inspected_at" value="{{ optional($inspection->inspected_at)->format('Y-m-d\TH:i') }}" style="width:100%;padding:8px"></label>
                                <label>Date autorisation client<br><input type="datetime-local" name="client_authorized_at" value="{{ optional($inspection->client_authorized_at)->format('Y-m-d\TH:i') }}" style="width:100%;padding:8px"></label>
                                <label>Date demande<br><input type="datetime-local" name="requested_at" value="{{ optional($inspection->requested_at)->format('Y-m-d\TH:i') }}" style="width:100%;padding:8px"></label>
                                <label>Date prévue<br><input type="datetime-local" name="planned_at" value="{{ optional($inspection->planned_at)->format('Y-m-d\TH:i') }}" style="width:100%;padding:8px"></label>
                                <label>Date réception<br><input type="datetime-local" name="received_at" value="{{ optional($inspection->received_at)->format('Y-m-d\TH:i') }}" style="width:100%;padding:8px"></label>
                                <label>Référence client<br><input name="client_reference" value="{{ $inspection->client_reference }}" style="width:100%;padding:8px"></label>
                                <label>Inspecteur<br><input name="inspector" value="{{ $inspection->inspector }}" style="width:100%;padding:8px"></label>
                                <label>Représentant client<br><input name="client_representative" value="{{ $inspection->client_representative }}" style="width:100%;padding:8px"></label>
                                <label style="grid-column:span 2">Source exigence<br><textarea name="requirement_source" rows="2" placeholder="CCTP / Design Basis / plan approuvé / procédure / norme..." style="width:100%;padding:8px">{{ $inspection->requirement_source }}</textarea></label>
                                <label style="grid-column:1/-1">Critères d’acceptation projet<br><textarea name="acceptance_criteria" rows="2" placeholder="Valeurs et tolérances du projet — aucune valeur n’est imposée automatiquement" style="width:100%;padding:8px">{{ $inspection->acceptance_criteria }}</textarea></label>
                                <label style="grid-column:1/-1">Notes<br><textarea name="notes" rows="2" style="width:100%;padding:8px">{{ $inspection->notes }}</textarea></label>
                            </div>
                            <button style="margin-top:9px;padding:8px 12px">Enregistrer inspection</button>
                        </form>
                    </div>

                    <div style="padding:14px 16px;border-top:1px solid #e2e8f0;background:#fafafa">
                        <h3 style="margin:0 0 10px;font-size:16px">PV / preuves (PDF, JPG, PNG, WEBP)</h3>
                        <form method="POST" enctype="multipart/form-data" action="{{ route('engineering.qa-control.evidences.store', [$organization->getKey(), $project->getKey(), $inspection->getKey()]) }}" style="display:grid;grid-template-columns:repeat(6,minmax(110px,1fr));gap:8px;align-items:end">
                            @csrf
                            <label>Référence<br><input name="reference" required style="width:100%;padding:7px"></label>
                            <label>Type document<br><input name="document_type" required placeholder="PV / rapport labo / photo" style="width:100%;padding:7px"></label>
                            <label>Date<br><input type="date" name="issued_at" required style="width:100%;padding:7px"></label>
                            <label>Révision<br><input name="revision" style="width:100%;padding:7px"></label>
                            <label>Statut<br><select name="status" style="width:100%;padding:7px"><option value="draft">Draft</option><option value="valid">Validé</option><option value="rejected">Rejeté</option></select></label>
                            <label>Fichier<br><input type="file" name="evidence" accept=".pdf,.jpg,.jpeg,.png,.webp" required style="width:100%"></label>
                            <label style="grid-column:1/-1">Notes<br><input name="notes" style="width:100%;padding:7px"></label>
                            <button style="grid-column:1/-1;padding:8px">Enregistrer la preuve</button>
                        </form>
                        @if($inspection->evidences->isNotEmpty())
                            <table style="width:100%;border-collapse:collapse;margin-top:10px;font-size:13px">
                                <tr><th align="left">Réf.</th><th align="left">Type</th><th>Date</th><th>Fichier</th><th>SHA-256</th><th></th></tr>
                                @foreach($inspection->evidences as $evidence)
                                    <tr style="border-top:1px solid #e2e8f0"><td>{{ $evidence->reference }}</td><td>{{ $evidence->document_type }}</td><td align="center">{{ optional($evidence->issued_at)->format('d/m/Y') }}</td><td>{{ $evidence->original_name }}</td><td><code title="{{ $evidence->sha256 }}">{{ substr($evidence->sha256,0,12) }}…</code></td><td><a href="{{ route('engineering.qa-control.evidences.download', [$organization->getKey(), $project->getKey(), $evidence->getKey()]) }}">Télécharger</a></td></tr>
                                @endforeach
                            </table>
                        @endif
                    </div>

                    @if($schema)
                        <div style="padding:14px 16px;border-top:1px solid #e2e8f0">
                            <h3 style="margin:0 0 10px;font-size:16px">Tableau de suivi — {{ $schema['label'] }}</h3>
                            <form method="POST" action="{{ route('engineering.qa-control.register-entries.store', [$organization->getKey(), $project->getKey(), $inspection->getKey()]) }}">
                                @csrf
                                <div style="display:grid;grid-template-columns:repeat(4,minmax(140px,1fr));gap:8px">
                                    <label>Référence ligne<br><input name="reference" required style="width:100%;padding:7px"></label>
                                    <label>Date<br><input type="date" name="entry_date" required style="width:100%;padding:7px"></label>
                                    <label>Qualité<br><select name="quality_status" style="width:100%;padding:7px"><option value="pending">En attente</option><option value="conforming">Conforme</option><option value="nonconforming">Non conforme</option><option value="conditional">Conditionnel</option></select></label>
                                    <label>Source exigence<br><input name="source_reference" style="width:100%;padding:7px"></label>
                                    @foreach($schema['fields'] as $field=>$label)
                                        <label>{{ $label }}<br><input name="payload[{{ $field }}]" style="width:100%;padding:7px"></label>
                                    @endforeach
                                    <label style="grid-column:1/-1">Notes<br><input name="notes" style="width:100%;padding:7px"></label>
                                </div>
                                <button style="margin-top:8px;padding:8px 12px">Ajouter au suivi</button>
                            </form>
                            @if($inspection->registerEntries->isNotEmpty())
                                <div style="overflow:auto;margin-top:10px">
                                    <table style="width:100%;border-collapse:collapse;font-size:12px;white-space:nowrap">
                                        <tr><th align="left">Réf.</th><th>Date</th><th>Statut</th>@foreach($schema['fields'] as $label)<th align="left">{{ $label }}</th>@endforeach</tr>
                                        @foreach($inspection->registerEntries as $entry)
                                            <tr style="border-top:1px solid #e2e8f0"><td>{{ $entry->reference }}</td><td>{{ optional($entry->entry_date)->format('d/m/Y') }}</td><td>{{ $entry->quality_status }}</td>@foreach(array_keys($schema['fields']) as $field)<td>{{ data_get($entry->payload, $field, '—') }}</td>@endforeach</tr>
                                        @endforeach
                                    </table>
                                </div>
                            @endif
                        </div>
                    @endif
                </article>
            @empty
                <div style="padding:25px;border:1px dashed #cbd5e1;border-radius:10px;color:#64748b">Aucun contrôle généré. Choisis un type de travaux ci-dessus.</div>
            @endforelse
        </main>

        <aside>
            <h2 style="font-size:20px;margin:0 0 12px">Handovers ({{ $handovers->count() }})</h2>
            @forelse($handovers as $handover)
                <article style="border:1px solid #e2e8f0;border-radius:10px;padding:14px;margin-bottom:14px;background:#fff">
                    <strong>{{ $handover->code }}</strong>
                    <div style="font-size:13px;color:#64748b;margin:4px 0 10px">{{ $handover->scope_label }}</div>
                    <div style="font-size:13px;margin-bottom:9px"><strong>Critères de remise :</strong><ul style="padding-left:18px">@foreach(($handover->criteria ?? []) as $criterion)<li>{{ $criterion }}</li>@endforeach</ul></div>
                    <form method="POST" action="{{ route('engineering.qa-control.handovers.update', [$organization->getKey(), $project->getKey(), $handover->getKey()]) }}">
                        @csrf
                        <label>Statut<br><select name="status" style="width:100%;padding:7px">@foreach(['draft'=>'Draft','requested'=>'Demandé','accepted'=>'Accepté','rejected'=>'Rejeté'] as $k=>$v)<option value="{{ $k }}" @selected($handover->status===$k)>{{ $v }}</option>@endforeach</select></label><br>
                        <label>Date demande<br><input type="datetime-local" name="requested_at" value="{{ optional($handover->requested_at)->format('Y-m-d\TH:i') }}" style="width:100%;padding:7px"></label><br>
                        <label>Date inspection<br><input type="datetime-local" name="inspected_at" value="{{ optional($handover->inspected_at)->format('Y-m-d\TH:i') }}" style="width:100%;padding:7px"></label><br>
                        <label>Autorisation client<br><input type="datetime-local" name="client_authorized_at" value="{{ optional($handover->client_authorized_at)->format('Y-m-d\TH:i') }}" style="width:100%;padding:7px"></label><br>
                        <label>Date acceptation<br><input type="datetime-local" name="accepted_at" value="{{ optional($handover->accepted_at)->format('Y-m-d\TH:i') }}" style="width:100%;padding:7px"></label><br>
                        <label>Réf. client<br><input name="client_reference" value="{{ $handover->client_reference }}" style="width:100%;padding:7px"></label><br>
                        <label>Responsable BA<br><input name="from_responsible" value="{{ $handover->from_responsible }}" style="width:100%;padding:7px"></label><br>
                        <label>Responsable CM<br><input name="to_responsible" value="{{ $handover->to_responsible }}" style="width:100%;padding:7px"></label><br>
                        <label>Client<br><input name="client_representative" value="{{ $handover->client_representative }}" style="width:100%;padding:7px"></label><br>
                        <label>Notes<br><textarea name="notes" rows="3" style="width:100%;padding:7px">{{ $handover->notes }}</textarea></label>
                        <button style="margin-top:7px;padding:8px 12px">Enregistrer handover</button>
                    </form>
                </article>
            @empty
                <div style="padding:18px;border:1px dashed #cbd5e1;border-radius:9px;color:#64748b">Le handover BA → charpente est créé automatiquement pour une structure mixte.</div>
            @endforelse
        </aside>
    </div>
</div>
@endsection
