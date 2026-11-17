@extends('layouts.app')

@section('content')
<div style="max-width:1500px;margin:0 auto;padding:24px;font-family:Arial,sans-serif">
    <div style="display:flex;justify-content:space-between;gap:16px;align-items:flex-start;margin-bottom:20px">
        <div>
            <div style="font-size:12px;text-transform:uppercase;letter-spacing:.08em;color:#64748b">Civilina Engineering · QA/QC</div>
            <h1 style="margin:5px 0 6px;font-size:28px">Agent normatif QA/QC</h1>
            <div style="color:#64748b">Contrôle → norme → section → exigence → validation humaine.</div>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <a href="{{ route('engineering.qa-control.index', [$organization->getRouteKey(), $project->getRouteKey()]) }}" style="padding:10px 14px;border:1px solid #cbd5e1;border-radius:7px;text-decoration:none;color:#0f172a">Contrôles & PV</a>
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

    <section style="border:1px solid #e2e8f0;border-radius:10px;padding:18px;margin-bottom:20px;background:#fff">
        <div style="display:flex;justify-content:space-between;gap:16px;align-items:center;flex-wrap:wrap">
            <div>
                <h2 style="margin:0 0 6px;font-size:19px">Dossier normes</h2>
                <div style="font-size:13px;color:#64748b">
                    <code style="word-break:break-all">{{ $inboxDirectory }}</code>
                </div>
                <div style="font-size:13px;color:#64748b;margin-top:4px">
                    Sections normatives indexées : <strong>{{ $standardSectionCount }}</strong>
                </div>
            </div>

            @civilinastaff
            <form method="POST" action="{{ route('engineering.qa-normative.scan', [$organization->getRouteKey(), $project->getRouteKey()]) }}" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                @csrf
                <select name="batch_size" style="padding:9px 8px;border:1px solid #cbd5e1;border-radius:7px">
                    <option value="10">10 fichiers</option>
                    <option value="20" selected>20 fichiers</option>
                    <option value="50">50 fichiers</option>
                </select>
                <button style="padding:10px 14px;border:0;border-radius:7px;background:#0f172a;color:#fff;font-weight:700;cursor:pointer">
                    Scanner le lot suivant
                </button>
            </form>
            @endcivilinastaff
        </div>

        @civilinastaff
        <div style="margin-top:12px;padding:10px 12px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:7px;font-size:13px">
            <strong>Scan par lots activé.</strong>
            Les fichiers déjà scannés et non modifiés sont ignorés.
            Pour un dossier volumineux : <code>php artisan civilina:standards-scan --all</code>.
        </div>

        @if($inboxFiles->isNotEmpty())
            <div style="overflow:auto;margin-top:14px">
                <table style="width:100%;border-collapse:collapse;font-size:12px">
                    <tr>
                        <th align="left">Fichier</th>
                        <th align="left">Référence détectée</th>
                        <th align="left">Statut</th>
                        <th align="left">Document Civilina</th>
                        <th align="right">Taille</th>
                    </tr>
                    @foreach($inboxFiles as $file)
                        <tr style="border-top:1px solid #e2e8f0">
                            <td style="padding:7px 4px">{{ $file->relative_path }}</td>
                            <td>{{ $file->detected_reference ?: '—' }}</td>
                            <td>{{ $file->status }}</td>
                            <td>{{ $file->standard_document_id ?: '—' }}</td>
                            <td align="right">{{ number_format(($file->size_bytes ?? 0)/1024, 0, ',', ' ') }} Ko</td>
                        </tr>
                    @endforeach
                </table>
            </div>
        @endif
        @endcivilinastaff

        <div style="margin-top:12px;padding:10px 12px;background:#fffbeb;border:1px solid #fde68a;border-radius:7px;font-size:13px">
            <strong>Important :</strong> le scan inventorie les fichiers et rapproche les documents.
            La recherche de clauses utilise les <strong>StandardSection</strong> déjà indexées.
        </div>
    </section>

    <h2 style="font-size:20px;margin:0 0 12px">Contrôles QA/QC ({{ $inspections->count() }})</h2>

    @forelse($inspections as $inspection)
        <article style="border:1px solid #e2e8f0;border-radius:10px;margin-bottom:16px;background:#fff;overflow:hidden">
            <div style="padding:14px 16px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;gap:12px;align-items:start">
                <div>
                    <strong>{{ $inspection->inspection_code }}</strong> — {{ $inspection->title }}
                    <div style="font-size:12px;color:#64748b;margin-top:4px">{{ $inspection->work_type }} · {{ $inspection->scope_label }}</div>
                </div>
                <form method="POST" action="{{ route('engineering.qa-normative.suggest', [$organization->getRouteKey(), $project->getRouteKey(), $inspection->getKey()]) }}">
                    @csrf
                    <input type="hidden" name="limit" value="5">
                    <button style="padding:9px 12px;border:0;border-radius:7px;background:#0f172a;color:#fff;cursor:pointer">Rechercher les références</button>
                </form>
            </div>

            <div style="padding:14px 16px">
                @if($inspection->normativeLinks->isEmpty())
                    <div style="padding:12px;border:1px dashed #cbd5e1;border-radius:7px;color:#64748b">Aucune référence liée.</div>
                @else
                    <div style="display:grid;gap:10px">
                        @foreach($inspection->normativeLinks as $link)
                            <div style="border:1px solid {{ $link->status === 'validated' ? '#86efac' : ($link->status === 'rejected' ? '#fca5a5' : '#cbd5e1') }};border-radius:8px;padding:12px">
                                <strong>{{ $link->source_reference ?: $link->source_label ?: 'Référence normative' }}</strong>
                                @if($link->clause_code)<span> · § {{ $link->clause_code }}</span>@endif
                                <div style="font-size:12px;color:#64748b;margin-top:4px">
                                    Pertinence : <strong>{{ number_format((float)$link->confidence_score, 0) }} %</strong>
                                    · Profil projet : <strong>{{ $link->project_profile_match ? 'oui' : 'non/indéterminé' }}</strong>
                                    · Statut : <strong>{{ $link->status }}</strong>
                                </div>

                                @if($link->excerpt)
                                    <details style="margin-top:9px">
                                        <summary style="cursor:pointer;font-size:13px">Voir l’extrait indexé</summary>
                                        <div style="margin-top:7px;padding:9px;background:#f8fafc;border-radius:6px;white-space:pre-wrap;font-size:12px">{{ $link->excerpt }}</div>
                                    </details>
                                @endif

                                @if($link->status === 'suggested')
                                    <div style="display:flex;gap:8px;margin-top:10px">
                                        <form method="POST" action="{{ route('engineering.qa-normative.decision', [$organization->getRouteKey(), $project->getRouteKey(), $link->getKey()]) }}">
                                            @csrf
                                            <input type="hidden" name="status" value="validated">
                                            <button style="padding:7px 10px">Valider</button>
                                        </form>
                                        <form method="POST" action="{{ route('engineering.qa-normative.decision', [$organization->getRouteKey(), $project->getRouteKey(), $link->getKey()]) }}">
                                            @csrf
                                            <input type="hidden" name="status" value="rejected">
                                            <button style="padding:7px 10px">Rejeter</button>
                                        </form>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </article>
    @empty
        <div style="padding:22px;border:1px dashed #cbd5e1;border-radius:9px;color:#64748b">Aucun contrôle QA/QC.</div>
    @endforelse
</div>
@endsection
