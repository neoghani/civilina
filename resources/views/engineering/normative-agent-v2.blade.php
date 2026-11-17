<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Agent normatif QA/QC · Civilina</title>
    <style>
        :root {
            --navy:#102f46;
            --navy2:#173f5c;
            --ink:#102235;
            --muted:#61768a;
            --bg:#f3f6f8;
            --card:#ffffff;
            --line:#d8e1e7;
            --soft:#edf4f8;
            --ok:#13795b;
            --warn:#b56a00;
            --bad:#b42318;
            --blue:#1769aa;
        }
        *{box-sizing:border-box}
        body{margin:0;font-family:Inter,ui-sans-serif,system-ui,-apple-system,Segoe UI,Arial,sans-serif;background:var(--bg);color:var(--ink)}
        a{color:inherit}
        .top{height:58px;background:white;border-bottom:1px solid var(--line);display:flex;align-items:center;justify-content:space-between;padding:0 28px}
        .brand{font-weight:800;letter-spacing:.02em}
        .shell{display:grid;grid-template-columns:230px 1fr;min-height:calc(100vh - 58px)}
        .side{background:var(--navy);color:white;padding:22px 14px}
        .side small{display:block;color:#a9c2d5;letter-spacing:.12em;margin:0 8px 12px}
        .side a{display:block;text-decoration:none;padding:10px 12px;border-radius:8px;margin:2px 0;font-size:14px}
        .side a:hover,.side a.active{background:rgba(255,255,255,.11)}
        .content{padding:26px 30px 60px;max-width:1650px;width:100%}
        h1{margin:0;font-size:28px}
        .subtitle{color:var(--muted);margin-top:5px}
        .actions{display:flex;gap:10px;flex-wrap:wrap}
        .headrow{display:flex;align-items:flex-start;justify-content:space-between;gap:20px;margin-bottom:20px}
        .btn{border:1px solid var(--line);background:white;border-radius:8px;padding:10px 14px;font-weight:650;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:6px}
        .btn.primary{background:var(--navy);color:white;border-color:var(--navy)}
        .btn.ok{background:var(--ok);color:white;border-color:var(--ok)}
        .btn.bad{background:white;color:var(--bad);border-color:#f0b7b3}
        .cards{display:grid;grid-template-columns:repeat(6,minmax(130px,1fr));gap:12px;margin:18px 0}
        .metric{background:white;border:1px solid var(--line);border-radius:10px;padding:14px}
        .metric span{font-size:11px;color:var(--muted);letter-spacing:.08em;text-transform:uppercase}
        .metric strong{display:block;font-size:26px;margin-top:5px}
        .tabs{display:flex;gap:5px;background:white;border:1px solid var(--line);border-radius:10px;padding:5px;margin-bottom:16px}
        .tabs a{text-decoration:none;padding:9px 13px;border-radius:7px;font-weight:650;font-size:14px}
        .tabs a.active{background:var(--navy);color:white}
        .panel{background:white;border:1px solid var(--line);border-radius:11px;padding:18px;margin-bottom:16px}
        .panel h2{font-size:18px;margin:0 0 12px}
        .message{padding:12px 14px;background:#eaf5ff;border:1px solid #b9dcfb;border-radius:8px;margin-bottom:15px}
        table{width:100%;border-collapse:collapse;font-size:13px}
        th{text-align:left;color:var(--muted);font-size:11px;text-transform:uppercase;letter-spacing:.04em;border-bottom:1px solid var(--line);padding:9px 8px}
        td{border-bottom:1px solid #edf0f2;padding:9px 8px;vertical-align:top}
        .code{font-family:ui-monospace,SFMono-Regular,Consolas,monospace;font-weight:650}
        .badge{display:inline-block;padding:3px 7px;border-radius:999px;background:var(--soft);font-size:11px}
        .badge.ok{background:#e4f4ee;color:var(--ok)}
        .badge.warn{background:#fff3dd;color:var(--warn)}
        .badge.bad{background:#fdeceb;color:var(--bad)}
        .inspection{border:1px solid var(--line);border-radius:10px;margin:10px 0;overflow:hidden}
        .inspection-head{padding:13px 14px;background:#f8fafb;display:flex;justify-content:space-between;gap:15px}
        .inspection-body{padding:14px}
        .link{border-left:3px solid var(--blue);padding:10px 12px;background:#fafcff;margin:8px 0}
        .link.validated{border-left-color:var(--ok);background:#f5fbf8}
        .link.rejected{border-left-color:var(--bad);opacity:.7}
        .grid2{display:grid;grid-template-columns:1fr 1fr;gap:14px}
        .muted{color:var(--muted)}
        .tiny{font-size:12px}
        .tol{font-family:ui-monospace,SFMono-Regular,Consolas,monospace;background:#f4f6f7;border-radius:5px;padding:3px 6px}
        form.inline{display:inline}
        select,input{padding:9px 10px;border:1px solid var(--line);border-radius:7px;background:white}
        @media(max-width:1100px){
            .shell{grid-template-columns:1fr}.side{display:none}.cards{grid-template-columns:repeat(3,1fr)}.grid2{grid-template-columns:1fr}
        }
    </style>
@include('components.civilina-command-theme')
</head>
<body>
@include('engineering._engineering-nav')

<div class="top">
    <div class="brand">CIVILINA ENGINEERING · QA/QC</div>
    <div class="actions">
        @if(\Illuminate\Support\Facades\Route::has('engineering.qa-control.index'))
            <a class="btn" href="{{ route('engineering.qa-control.index', ['organization'=>$organization, 'project'=>$project]) }}">Contrôles & PV</a>
        @endif
        @if(\Illuminate\Support\Facades\Route::has('engineering.workspace'))
            <a class="btn" href="{{ route('engineering.workspace.bridge',['organization'=>$organization,'project'=>$project]) }}">Workspace</a>
        @endif
    </div>
</div>

<div class="shell">
    <aside class="side">
        <small>PROJET</small>
        <a href="?tab=registry" class="{{ $tab==='registry'?'active':'' }}">Base normative</a>
        <a href="?tab=links" class="{{ $tab==='links'?'active':'' }}">Liaisons QA/QC</a>
        <a href="?tab=duplicates" class="{{ $tab==='duplicates'?'active':'' }}">Doublons & versions</a>
        <a href="?tab=inbox" class="{{ $tab==='inbox'?'active':'' }}">Inbox / Import</a>
    </aside>

    <main class="content">
        <div class="headrow">
            <div>
                <div class="muted tiny">CIVILINA · AGENT NORMATIF V2</div>
                <h1>Base normative & liaison QA/QC</h1>
                <div class="subtitle">Référence → édition → section / article → exigence ou tolérance → contrôle QA/QC → validation humaine.</div>
            </div>
            <div class="actions">
                @civilinastaff
                <form method="post" action="{{ route('engineering.normative-v2.scan', ['organization'=>$organization,'project'=>$project]) }}">
                    @csrf
                    <input type="hidden" name="limit" value="50">
                    <button class="btn" type="submit">Indexer 50 fichiers</button>
                </form>
                @endcivilinastaff
                <form method="post" action="{{ route('engineering.normative-v2.run', ['organization'=>$organization,'project'=>$project]) }}">
                    @csrf
                    <button class="btn primary" type="submit">Lancer l’agent</button>
                </form>
            </div>
        </div>

        @if(session('normative_message'))
            <div class="message">{{ session('normative_message') }}</div>
        @endif

        <div class="cards">
            <div class="metric"><span>Références canoniques</span><strong>{{ $counts['standards'] }}</strong></div>
            <div class="metric"><span>Sources</span><strong>{{ $counts['sources'] }}</strong></div>
            <div class="metric"><span>Exigences / tolérances</span><strong>{{ $counts['requirements'] }}</strong></div>
            <div class="metric"><span>Doublons exacts</span><strong>{{ $counts['duplicates'] }}</strong></div>
            <div class="metric"><span>À traiter</span><strong>{{ $counts['pending'] }}</strong></div>
            <div class="metric"><span>Liens QA/QC</span><strong>{{ $counts['links'] }}</strong></div>
        </div>

        <nav class="tabs">
            <a href="?tab=registry" class="{{ $tab==='registry'?'active':'' }}">Base normative</a>
            <a href="?tab=links" class="{{ $tab==='links'?'active':'' }}">Liaisons QA/QC</a>
            <a href="?tab=duplicates" class="{{ $tab==='duplicates'?'active':'' }}">Doublons & versions</a>
            <a href="?tab=inbox" class="{{ $tab==='inbox'?'active':'' }}">Inbox / Import</a>
        </nav>

        @if($tab === 'registry')
            <section class="panel">
                <h2>Base normative Civilina</h2>
                <p class="muted">Une ligne = une référence canonique. Les copies de fichiers, alias et éditions restent reliés sans être confondus.</p>
                <table>
                    <thead>
                    <tr>
                        <th>Référence</th><th>Titre</th><th>Domaine</th><th>Type</th><th>Éditions</th><th>Sources</th><th>Sections</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($standards as $standard)
                        <tr>
                            <td class="code">{{ $standard->canonical_code ?: '—' }}</td>
                            <td>{{ $standard->title ?: 'Titre à confirmer' }}</td>
                            <td><span class="badge">{{ $standard->domain ?: 'non classé' }}</span></td>
                            <td>{{ $standard->document_type }}</td>
                            <td>{{ $standard->editions_count }}</td>
                            <td>{{ $standard->sources_count }}</td>
                            <td>{{ $standard->sections_count }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7">Aucune référence indexée. Lance l’indexation.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </section>
        @elseif($tab === 'links')
            <section class="panel">
                <h2>Liaisons Contrôle ↔ Référence ↔ Exigence / Tolérance</h2>
                <p class="muted">L’agent propose uniquement à partir du contenu réellement indexé. La validation humaine rend la référence applicable au projet.</p>

                @forelse($inspections as $inspection)
                    <article class="inspection">
                        <div class="inspection-head">
                            <div>
                                <strong class="code">{{ $inspection->code ?? ('Inspection #'.$inspection->id) }}</strong>
                                <div>{{ $inspection->title ?? $inspection->name ?? $inspection->required_record ?? 'Contrôle QA/QC' }}</div>
                                <div class="muted tiny">Type de travaux : {{ $inspection->work_type ?? '—' }}</div>
                                @if(!($activityCompatibility[$inspection->id] ?? true))
                                    <div class="badge bad">Activité incohérente avec le type de travaux — ignorée par l’agent</div>
                                @endif
                            </div>
                            <form method="post" action="{{ route('engineering.normative-v2.suggest', ['organization'=>$organization,'project'=>$project,'inspection'=>$inspection]) }}">
                                @csrf
                                <button class="btn" type="submit">Rechercher les références</button>
                            </form>
                        </div>
                        <div class="inspection-body">
                            @forelse(($links[$inspection->id] ?? collect()) as $link)
                                <div class="link {{ $link->status }}">
                                    <div class="grid2">
                                        <div>
                                            <strong class="code">{{ $link->standard?->canonical_code }}</strong>
                                            <span class="badge">{{ $link->standard?->domain }}</span>
                                            <div class="tiny muted">
                                                Édition : {{ $link->edition?->edition_label ?? 'non détectée' }}
                                                · Section / article : {{ $link->section?->section_code ?? '—' }}
                                                {{ $link->section?->heading ? ' — '.$link->section->heading : '' }}
                                            </div>
                                            <p><strong>Exigence / tolérance extraite :</strong><br>{{ $link->requirement?->requirement_text ?? 'Section pertinente, exigence structurée non détectée.' }}</p>
                                            @if($link->requirement)
                                                <div>
                                                    @if($link->requirement->operator)
                                                        <span class="tol">{{ $link->requirement->operator }}</span>
                                                    @endif
                                                    @if($link->requirement->min_value !== null)
                                                        <span class="tol">min {{ $link->requirement->min_value }} {{ $link->requirement->unit }}</span>
                                                    @endif
                                                    @if($link->requirement->max_value !== null)
                                                        <span class="tol">max {{ $link->requirement->max_value }} {{ $link->requirement->unit }}</span>
                                                    @endif
                                                    @if($link->requirement->nominal_value !== null)
                                                        <span class="tol">{{ $link->requirement->nominal_value }} {{ $link->requirement->unit }}</span>
                                                    @endif
                                                    @if($link->requirement->tolerance_plus !== null)
                                                        <span class="tol">± {{ $link->requirement->tolerance_plus }} {{ $link->requirement->unit }}</span>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>
                                        <div>
                                            <div><strong>Score :</strong> {{ $link->score }} %</div>
                                            <div class="tiny muted">{{ $link->reason }}</div>
                                            <div style="margin-top:10px">
                                                <span class="badge {{ $link->status==='validated'?'ok':($link->status==='rejected'?'bad':'warn') }}">{{ $link->status }}</span>
                                            </div>
                                            @if($link->status === 'proposed')
                                                <div class="actions" style="margin-top:12px">
                                                    <form class="inline" method="post" action="{{ route('engineering.normative-v2.links.validate', ['organization'=>$organization,'project'=>$project,'link'=>$link]) }}">
                                                        @csrf
                                                        <button class="btn ok" type="submit">Valider</button>
                                                    </form>
                                                    <form class="inline" method="post" action="{{ route('engineering.normative-v2.links.reject', ['organization'=>$organization,'project'=>$project,'link'=>$link]) }}">
                                                        @csrf
                                                        <button class="btn bad" type="submit">Rejeter</button>
                                                    </form>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="muted">Aucune référence liée.</div>
                            @endforelse
                        </div>
                    </article>
                @empty
                    <div class="muted">Aucun contrôle QA/QC pour ce projet.</div>
                @endforelse
            </section>
        @elseif($tab === 'duplicates')
            <section class="panel">
                <h2>Doublons & versions</h2>
                <p class="muted">Le SHA-256 identifie les copies exactes. La référence canonique regroupe les copies logiques ; les éditions restent distinctes.</p>
                <table>
                    <thead><tr><th>Fichier</th><th>SHA-256</th><th>Référence</th><th>Statut</th><th>Original</th></tr></thead>
                    <tbody>
                    @foreach($sources->where('is_exact_duplicate', true) as $source)
                        <tr>
                            <td>{{ $source->relative_path }}</td>
                            <td class="code">{{ substr((string)$source->sha256,0,16) }}…</td>
                            <td class="code">{{ $source->detected_code ?: '—' }}</td>
                            <td><span class="badge warn">doublon exact</span></td>
                            <td>{{ $source->duplicateOf?->relative_path ?: '—' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </section>
        @else
            <section class="panel">
                <h2>Inbox / Import</h2>
                <p class="muted">Cette vue est l’entrée documentaire. Les CV et lettres sont rejetés ; les fiches techniques sont classées séparément ; seules les normes/référentiels alimentent le moteur normatif.</p>
                <table>
                    <thead><tr><th>Fichier</th><th>Référence détectée</th><th>Type</th><th>Extraction</th><th>Référence Civilina</th><th>Doublon</th></tr></thead>
                    <tbody>
                    @foreach($sources as $source)
                        <tr>
                            <td>{{ $source->relative_path }}</td>
                            <td class="code">{{ $source->detected_code ?: '—' }}</td>
                            <td><span class="badge">{{ $source->document_type }}</span></td>
                            <td>
                                <span class="badge {{ in_array($source->extraction_status,['extracted','exact_duplicate'])?'ok':($source->extraction_status==='rejected'?'bad':'warn') }}">
                                    {{ $source->extraction_status }}
                                </span>
                                @if($source->rejection_reason)
                                    <div class="tiny muted">{{ $source->rejection_reason }}</div>
                                @endif
                            </td>
                            <td class="code">{{ $source->standard?->canonical_code ?: '—' }}</td>
                            <td>{{ $source->is_exact_duplicate ? 'Oui' : 'Non' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </section>
        @endif
    </main>
</div>
</body>
</html>
