<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>QA/QC · ITP · Civilina Engineering</title>
    <style>
        :root{--navy:#123149;--navy2:#1d4c6c;--ink:#101820;--muted:#64748b;--line:#d9e2ea;--bg:#f4f7f9;--ok:#166534;--warn:#92400e;--danger:#b42318}
        *{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font-family:Arial,Helvetica,sans-serif}.shell{display:grid;grid-template-columns:240px minmax(0,1fr);min-height:100vh}
        .side{background:var(--navy);color:#fff;padding:22px 16px}.brand{font-size:20px;font-weight:800;margin-bottom:28px}.brand small{display:block;font-size:11px;letter-spacing:.08em}.project{padding:14px 0 18px;border-top:1px solid #395469;border-bottom:1px solid #395469;margin-bottom:18px}.project span{display:block;opacity:.72;font-size:11px;text-transform:uppercase;margin-bottom:6px}.nav a{display:block;color:#fff;text-decoration:none;padding:11px 12px;border-radius:7px;margin:4px 0;font-size:14px}.nav a:hover,.nav a.active{background:#294b63}
        main{min-width:0}.top{background:#fff;border-bottom:1px solid var(--line);padding:18px 28px}.top h1{margin:0 0 4px;font-size:21px}.top p{margin:0;color:var(--muted);font-size:13px}.content{padding:28px;max-width:1550px}.head{margin-bottom:18px}.head h2{margin:0 0 5px;font-size:22px}.head p{margin:0;color:var(--muted)}
        .grid{display:grid;grid-template-columns:minmax(0,1.5fr) minmax(350px,.75fr);gap:18px;align-items:start}.card{background:#fff;border:1px solid var(--line);border-radius:10px;box-shadow:0 10px 28px rgba(20,45,65,.05)}.pad{padding:18px}.card h3{margin:0 0 14px}.form{display:grid;gap:11px}.row{display:grid;grid-template-columns:1fr 1fr;gap:10px}
        label{display:grid;gap:5px;font-size:11px;font-weight:800;color:#425466;text-transform:uppercase;letter-spacing:.04em}input,select,textarea{width:100%;border:1px solid #ccd8e1;border-radius:6px;padding:10px 11px;font:inherit;background:#fff}textarea{min-height:84px;resize:vertical}button{border:0;border-radius:6px;background:var(--navy2);color:#fff;font-weight:800;padding:10px 14px;cursor:pointer}button.secondary{background:#fff;color:var(--navy2);border:1px solid var(--navy2)}button.approve{background:var(--ok)}
        table{width:100%;border-collapse:collapse;font-size:13px}th,td{padding:10px 12px;border-top:1px solid var(--line);text-align:left;vertical-align:top}th{color:#506578;background:#f8fafc;font-size:10px;text-transform:uppercase;letter-spacing:.05em}.status{display:inline-block;padding:4px 7px;border-radius:999px;font-size:10px;font-weight:800;text-transform:uppercase;background:#eef2f5}.status.approved{background:#dcfce7;color:var(--ok)}.status.in_review{background:#fef3c7;color:var(--warn)}
        .itp{margin-bottom:18px;overflow:hidden}.itp-head{display:flex;align-items:flex-start;justify-content:space-between;gap:15px;padding:16px 18px}.itp-head strong{display:block;font-size:16px;margin-bottom:4px}.meta{color:var(--muted);font-size:12px}.refs{margin:7px 0 0;padding-left:18px;color:var(--muted);font-size:12px}.readiness{margin:0 18px 16px;padding:10px 12px;border-radius:7px;font-size:12px;border:1px solid var(--line);background:#f8fafc}.readiness.ok{color:var(--ok);border-color:#bbf7d0;background:#f0fdf4}.readiness.blocked{color:var(--warn);border-color:#fde68a;background:#fffbeb}.actions{display:flex;gap:8px;flex-wrap:wrap;padding:0 18px 18px}.add-row{border-top:1px solid var(--line);padding:18px;background:#fbfdfe}.alert{margin-bottom:14px;padding:12px 14px;border-radius:7px}.alert.ok{background:#ecfdf3;color:var(--ok);border:1px solid #bbf7d0}.alert.err{background:#fff1f2;color:var(--danger);border:1px solid #fecdd3}.empty{padding:34px 18px;text-align:center;color:var(--muted)}
        @media(max-width:1000px){.shell{grid-template-columns:1fr}.side{display:none}.grid{grid-template-columns:1fr}.row{grid-template-columns:1fr}.content{padding:18px}}
    </style>
@include('components.civilina-command-theme')
</head>
<body>
@include('engineering._engineering-nav')

<div class="shell">
    <aside class="side">
        <div class="brand">Civilina <small>ENGINEERING</small></div>
        <div class="project"><span>Projet</span><strong>{{ $project->code ?? $project->name ?? $project->getKey() }}</strong></div>
        <nav class="nav">
            <a href="{{ route('engineering.workspace.bridge',['organization'=>$organization,'project'=>$project]) }}">Vue Engineering</a>
            <a class="active" href="{{ route('engineering.itp.index', [$organization->getRouteKey(), $project->getRouteKey()]) }}">QA/QC · ITP</a>
        </nav>
    </aside>

    <main>
        <header class="top">
            <h1>{{ $project->name ?? 'Projet Civilina Engineering' }}</h1>
            <p>Source de vérité QA/QC · Inspection & Test Plan</p>
        </header>

        <div class="content">
            <div class="head">
                <h2>QA/QC — Inspection & Test Plans</h2>
                <p>Contrôles, critères d’acceptation, Hold/Witness Points, responsabilités et preuves — sans invention automatique d’exigences techniques.</p>
            </div>

            @if(session('success'))<div class="alert ok">{{ session('success') }}</div>@endif
            @if($errors->any())
                <div class="alert err"><strong>Correction nécessaire</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif

            <div class="grid">
                <section>
                    @forelse($itps as $itp)
                        @php($ready = $itp->getAttribute('readiness'))
                        <article class="card itp">
                            <div class="itp-head">
                                <div>
                                    <strong>{{ $itp->code }} — {{ $itp->title }}</strong>
                                    <div class="meta">Rév. {{ $itp->revision }} · {{ $itp->scope_type }} : {{ $itp->scope_label }} @if($itp->scope_ref) · {{ $itp->scope_ref }} @endif</div>
                                    <ul class="refs">@foreach(($itp->reference_documents ?? []) as $reference)<li>{{ $reference }}</li>@endforeach</ul>
                                </div>
                                <span class="status {{ $itp->status }}">{{ $itp->status }}</span>
                            </div>

                            <div class="readiness {{ $ready['ready'] ? 'ok' : 'blocked' }}">
                                @if($ready['ready']) Prêt pour revue — {{ $ready['row_count'] }} ligne(s) structurée(s).
                                @else <strong>Bloquants :</strong> {{ implode(' · ', $ready['blockers']) }} @endif
                            </div>

                            @if($itp->rows->isNotEmpty())
                                <div style="overflow:auto">
                                    <table>
                                        <thead><tr><th>Code</th><th>Inspection / essai</th><th>Méthode / fréquence</th><th>Critère</th><th>Point</th><th>Responsabilités</th><th>Preuve</th></tr></thead>
                                        <tbody>
                                        @foreach($itp->rows as $row)
                                            <tr>
                                                <td>{{ $row->code }}</td>
                                                <td>{{ $row->inspection_test }}</td>
                                                <td>{{ $row->method ?: '—' }} @if($row->frequency)<br><span class="meta">{{ $row->frequency }}</span>@endif</td>
                                                <td>{{ $row->acceptance_criteria }}</td>
                                                <td><span class="status">{{ $row->checkpoint_type }}</span></td>
                                                <td>Exécute : {{ $row->responsibility_execute }}<br>Inspecte : {{ $row->responsibility_inspect }}@if($row->responsibility_approve)<br>Approuve : {{ $row->responsibility_approve }}@endif @if($row->responsibility_accept)<br>Accepte : {{ $row->responsibility_accept }}@endif</td>
                                                <td>{{ $row->record_required }} @if($row->source_reference)<br><span class="meta">Source : {{ $row->source_reference }}</span>@endif</td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif

                            @if($itp->isDraft())
                                <div class="add-row">
                                    <h3>Ajouter une ligne de contrôle</h3>
                                    <form class="form" method="post" action="{{ route('engineering.itp.rows.store', [$organization->getRouteKey(), $project->getRouteKey(), $itp]) }}">
                                        @csrf
                                        <div class="row"><label>Code<input name="code" required placeholder="ITP-STR-001-010"></label><label>Séquence<input name="sequence" type="number" min="1" placeholder="10"></label></div>
                                        <label>Inspection / essai<textarea name="inspection_test" required placeholder="Contrôle du ferraillage avant bétonnage"></textarea></label>
                                        <div class="row"><label>Méthode<input name="method" placeholder="Inspection visuelle / mesure / essai labo"></label><label>Fréquence<input name="frequency" placeholder="Chaque poteau / chaque lot / ..."></label></div>
                                        <label>Critère d’acceptation<textarea name="acceptance_criteria" required placeholder="Critère explicitement validé selon plan, spécification ou norme"></textarea></label>
                                        <div class="row"><label>Point qualité<select name="checkpoint_type" required><option value="HP">Hold Point (HP)</option><option value="WP">Witness Point (WP)</option><option value="SURVEILLANCE">Surveillance</option></select></label><label>Enregistrement / preuve<input name="record_required" required placeholder="Fiche contrôle, PV, rapport labo..."></label></div>
                                        <div class="row"><label>Exécute<input name="responsibility_execute" required placeholder="Entreprise / équipe"></label><label>Inspecte<input name="responsibility_inspect" required placeholder="QA/QC"></label></div>
                                        <div class="row"><label>Approuve<input name="responsibility_approve" placeholder="Responsable qualité / ingénieur"></label><label>Accepte<input name="responsibility_accept" placeholder="Client / MOE"></label></div>
                                        <label>Référence source<input name="source_reference" placeholder="Plan, clause, procédure, norme..."></label>
                                        <button type="submit">Ajouter la ligne</button>
                                    </form>
                                </div>
                            @endif

                            <div class="actions">
                                @if($itp->isDraft() && $ready['ready'])<form method="post" action="{{ route('engineering.itp.submit-review', [$organization->getRouteKey(), $project->getRouteKey(), $itp]) }}">@csrf<button class="secondary" type="submit">Soumettre en revue</button></form>@endif
                                @if($itp->isInReview())<form method="post" action="{{ route('engineering.itp.approve', [$organization->getRouteKey(), $project->getRouteKey(), $itp]) }}">@csrf<button class="approve" type="submit">Approuver l’ITP</button></form>@endif
                            </div>
                        </article>
                    @empty
                        <div class="card empty">Aucun ITP enregistré pour ce projet.</div>
                    @endforelse
                </section>

                <aside class="card pad">
                    <h3>Nouvel ITP</h3>
                    <form class="form" method="post" action="{{ route('engineering.itp.store', [$organization->getRouteKey(), $project->getRouteKey()]) }}">
                        @csrf
                        <div class="row"><label>Code<input name="code" value="{{ old('code') }}" required placeholder="ITP-STR-001"></label><label>Révision<input name="revision" value="{{ old('revision', 'DRAFT-01') }}"></label></div>
                        <label>Titre<input name="title" value="{{ old('title') }}" required placeholder="ITP Ferraillage et bétonnage poteaux"></label>
                        <div class="row"><label>Type de périmètre<select name="scope_type" required>@foreach(['project'=>'Projet','work_package'=>'Work Package','activity'=>'Activity','zone'=>'Zone','ouvrage'=>'Ouvrage'] as $value=>$label)<option value="{{ $value }}" @selected(old('scope_type')===$value)>{{ $label }}</option>@endforeach</select></label><label>Référence périmètre<input name="scope_ref" value="{{ old('scope_ref') }}" placeholder="WP-STR-001 / ACT-STR-001"></label></div>
                        <label>Libellé du périmètre<input name="scope_label" value="{{ old('scope_label') }}" required placeholder="Ferraillage poteaux"></label>
                        <label>Références applicables — une par ligne<textarea name="reference_documents_text" required placeholder="Plan STR-001 Rev.B&#10;Spécification béton SP-04&#10;EN 206">{{ old('reference_documents_text') }}</textarea></label>
                        <label>Notes<textarea name="notes" placeholder="Hypothèses, limites, points à confirmer...">{{ old('notes') }}</textarea></label>
                        <button type="submit">Créer le brouillon ITP</button>
                    </form>
                </aside>
            </div>
        </div>
    </main>
</div>
</body>
</html>
