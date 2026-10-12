<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Civilina Engineering</title>
<style>
:root{
    --ink:#102235;
    --muted:#6d7d89;
    --bg:#f6f8f9;
    --panel:#ffffff;
    --line:#dce4e9;
    --line-2:#c8d5dd;
    --navy:#123a52;
    --soft:#eef3f6;
    --soft-2:#f9fbfc;
    --ok:#2d6f5c;
}
*{box-sizing:border-box}
body{
    margin:0;
    background:var(--bg);
    font-family:Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",Arial,sans-serif;
    color:var(--ink);
}
a{color:inherit}
main{
    max-width:1220px;
    margin:auto;
    padding:34px 20px 70px;
}
.eyebrow{
    font-size:10px;
    text-transform:uppercase;
    letter-spacing:.12em;
    color:var(--muted);
}
.hero{
    display:grid;
    grid-template-columns:1.35fr .65fr;
    gap:20px;
    align-items:end;
    margin-bottom:24px;
}
h1{
    margin:6px 0 9px;
    font-size:36px;
    line-height:1.08;
    max-width:800px;
}
.hero p{
    margin:0;
    max-width:770px;
    color:var(--muted);
    line-height:1.55;
    font-size:14px;
}
.hero-kpis{
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:8px;
}
.kpi{
    background:var(--panel);
    border:1px solid var(--line);
    border-radius:10px;
    padding:12px;
}
.kpi strong{
    display:block;
    font-size:18px;
    margin-bottom:2px;
}
.kpi span{
    display:block;
    color:var(--muted);
    font-size:9px;
    text-transform:uppercase;
    letter-spacing:.07em;
}
.panel{
    background:var(--panel);
    border:1px solid var(--line);
    border-radius:14px;
    overflow:hidden;
}
.panel-head{
    display:flex;
    justify-content:space-between;
    gap:18px;
    align-items:flex-end;
    padding:18px 20px 14px;
    border-bottom:1px solid var(--line);
}
.panel-head h2{
    margin:2px 0 0;
    font-size:18px;
}
.panel-head p{
    margin:4px 0 0;
    color:var(--muted);
    font-size:12px;
}
.tags{
    display:flex;
    flex-wrap:wrap;
    gap:6px;
}
.tag{
    border:1px solid var(--line);
    border-radius:999px;
    padding:4px 8px;
    color:var(--muted);
    font-size:9px;
    background:#fff;
}
.graph-wrap{
    position:relative;
    padding:28px 28px 32px;
    min-height:520px;
    background:
        linear-gradient(to right, rgba(18,58,82,.03) 1px, transparent 1px),
        linear-gradient(to bottom, rgba(18,58,82,.03) 1px, transparent 1px),
        #fbfcfd;
    background-size:32px 32px;
}
.graph{
    position:relative;
    width:100%;
    min-height:455px;
}
.graph-lines{
    position:absolute;
    inset:0;
    width:100%;
    height:100%;
    pointer-events:none;
}
.graph-lines path{
    fill:none;
    stroke:#aebfc9;
    stroke-width:1.35;
}
.graph-lines .main{
    stroke:#8097a5;
    stroke-width:1.7;
}
.graph-lines .dash{
    stroke-dasharray:4 5;
}
.node{
    position:absolute;
    display:block;
    width:190px;
    min-height:104px;
    padding:13px 14px;
    border:1px solid var(--line-2);
    border-radius:12px;
    background:rgba(255,255,255,.97);
    text-decoration:none;
    box-shadow:0 6px 18px rgba(19,53,74,.035);
}
a.node:hover{
    border-color:#8ca4b2;
    box-shadow:0 9px 24px rgba(19,53,74,.08);
    transform:translateY(-1px);
}
.node .topline{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:8px;
    margin-bottom:8px;
}
.node .n{
    font-size:9px;
    color:var(--muted);
    text-transform:uppercase;
    letter-spacing:.08em;
}
.node .state{
    width:8px;
    height:8px;
    border-radius:999px;
    background:#9bb0bc;
}
.node.active .state{background:var(--ok)}
.node strong{
    display:block;
    font-size:14px;
    line-height:1.22;
    margin-bottom:4px;
}
.node p{
    margin:0;
    color:var(--muted);
    font-size:10px;
    line-height:1.35;
}
.node .open{
    margin-top:8px;
    font-size:9px;
    font-weight:800;
    color:var(--navy);
}
.node.project{
    width:210px;
    min-height:122px;
    background:var(--navy);
    color:#fff;
    border-color:var(--navy);
}
.node.project .n,
.node.project p{
    color:rgba(255,255,255,.68);
}
.node.project .state{background:#fff}
.node.output{
    background:#f1f6f4;
    border-color:#c9dcd5;
}
.node.foundation{
    background:#f8fafb;
}
.node.workspace{left:1.5%;top:42%}
.node.project{left:21.5%;top:39%}
.node.normes{left:43%;top:8%}
.node.itp{left:43%;top:39%}
.node.controls{left:64.5%;top:39%}
.node.trace{left:64.5%;top:8%}
.node.dossier{right:1%;top:39%}
.node.librarian{left:21.5%;bottom:2%}
.node.review{left:43%;bottom:2%}
.node.library{left:64.5%;bottom:2%}
.flow-strip{
    display:grid;
    grid-template-columns:repeat(6,1fr);
    border-top:1px solid var(--line);
    background:#fff;
}
.flow-step{
    padding:12px 13px;
    border-right:1px solid var(--line);
}
.flow-step:last-child{border-right:0}
.flow-step b{
    display:block;
    font-size:10px;
    margin-bottom:3px;
}
.flow-step span{
    color:var(--muted);
    font-size:9px;
}
.section{
    margin-top:20px;
}
.section-title{
    display:flex;
    align-items:flex-end;
    justify-content:space-between;
    gap:12px;
    margin-bottom:10px;
}
.section-title h2{
    margin:0;
    font-size:18px;
}
.section-title p{
    margin:3px 0 0;
    color:var(--muted);
    font-size:11px;
}
.logic-grid{
    display:grid;
    grid-template-columns:1fr 1fr 1fr;
    gap:10px;
}
.logic-card{
    background:#fff;
    border:1px solid var(--line);
    border-radius:11px;
    padding:14px;
    min-height:126px;
}
.logic-card .label{
    font-size:9px;
    color:var(--muted);
    text-transform:uppercase;
    letter-spacing:.08em;
    margin-bottom:7px;
}
.logic-card strong{
    display:block;
    font-size:14px;
    margin-bottom:5px;
}
.logic-card p{
    margin:0;
    color:var(--muted);
    font-size:10px;
    line-height:1.4;
}
.logic-card .mini{
    margin-top:11px;
    display:flex;
    align-items:center;
    gap:6px;
    flex-wrap:wrap;
}
.logic-card .mini span{
    border:1px solid var(--line);
    border-radius:6px;
    padding:4px 6px;
    font-size:9px;
    background:var(--soft-2);
}
.logic-card .mini i{
    color:#9baab4;
    font-style:normal;
}
.note{
    margin-top:12px;
    padding:11px 13px;
    border-left:3px solid var(--navy);
    background:#fff;
    border-top:1px solid var(--line);
    border-right:1px solid var(--line);
    border-bottom:1px solid var(--line);
    border-radius:9px;
    color:var(--muted);
    font-size:10px;
    line-height:1.45;
}
@media(max-width:1050px){
    .hero{grid-template-columns:1fr}
    .hero-kpis{max-width:520px}
    .graph-wrap{overflow-x:auto}
    .graph{min-width:1050px}
}
@media(max-width:760px){
    main{padding:23px 12px 50px}
    h1{font-size:29px}
    .hero-kpis{grid-template-columns:1fr 1fr 1fr}
    .panel-head{align-items:flex-start;flex-direction:column}
    .graph-wrap{
        padding:12px;
        min-height:auto;
        overflow:visible;
        background:#fbfcfd;
    }
    .graph{
        min-width:0;
        min-height:auto;
        display:grid;
        grid-template-columns:1fr;
        gap:9px;
    }
    .graph-lines{display:none}
    .node,
    .node.project{
        position:static;
        width:100%;
        min-height:auto;
    }
    .node.workspace,
    .node.project,
    .node.normes,
    .node.itp,
    .node.controls,
    .node.trace,
    .node.dossier,
    .node.librarian,
    .node.review,
    .node.library{
        left:auto;right:auto;top:auto;bottom:auto;
    }
    .flow-strip{
        grid-template-columns:1fr 1fr 1fr;
    }
    .logic-grid{grid-template-columns:1fr}
}
@media(max-width:480px){
    .hero-kpis{grid-template-columns:1fr}
    .flow-strip{grid-template-columns:1fr 1fr}
}
</style>
@include('components.civilina-command-theme')
</head>
<body>
@include('engineering._engineering-nav')

@php
    $moduleMap = collect($modules)->keyBy('label');

    $moduleUrl = function(string $label) use ($moduleMap) {
        return optional($moduleMap->get($label))['url'] ?? null;
    };

    $workspace = $moduleUrl('Workspace');
    $itp = $moduleUrl('ITP');
    $controls = $moduleUrl('Contrôles & PV');
    $library = $moduleUrl('Bibliothèque normative');
    $librarian = $moduleUrl('Normative Librarian');
    $links = $moduleUrl('Liaisons QA/QC ↔ Normes');
    $review = $moduleUrl('Revue canonique');
@endphp

<main>
    <section class="hero">
        <div>
            <div class="eyebrow">Civilina Engineering · SaaS Project Graph</div>
            <h1>Une lecture visuelle du projet, de la structure jusqu’au dossier d’ingénierie.</h1>
            <p>
                Le graphe montre comment les données du projet, les référentiels, les contrôles et les preuves
                se connectent. Le client comprend le système avant même d’ouvrir les modules.
            </p>
        </div>

        <div class="hero-kpis">
            <div class="kpi">
                <strong>01</strong>
                <span>Projet central</span>
            </div>
            <div class="kpi">
                <strong>06</strong>
                <span>Étapes de pilotage</span>
            </div>
            <div class="kpi">
                <strong>03</strong>
                <span>Couches normatives</span>
            </div>
        </div>
    </section>

    <section class="panel">
        <div class="panel-head">
            <div>
                <div class="eyebrow">Vue système</div>
                <h2>Graphe de pilotage Civilina Engineering</h2>
                <p>Les blocs cliquables ouvrent les modules disponibles.</p>
            </div>
            <div class="tags">
                <span class="tag">Projet</span>
                <span class="tag">QA/QC</span>
                <span class="tag">Normes</span>
                <span class="tag">Traçabilité</span>
                <span class="tag">Dossier final</span>
            </div>
        </div>

        <div class="graph-wrap">
            <div class="graph">
                <svg class="graph-lines" viewBox="0 0 1100 455" preserveAspectRatio="none" aria-hidden="true">
                    <defs>
                        <marker id="arrow" markerWidth="7" markerHeight="7" refX="6" refY="3.5" orient="auto">
                            <path d="M0,0 L7,3.5 L0,7" fill="none" stroke="#8097a5" stroke-width="1.2"/>
                        </marker>
                    </defs>

                    <path class="main" d="M185 255 H245" marker-end="url(#arrow)"/>
                    <path class="main" d="M420 255 H485" marker-end="url(#arrow)"/>
                    <path class="main" d="M660 255 H720" marker-end="url(#arrow)"/>
                    <path class="main" d="M895 255 H955" marker-end="url(#arrow)"/>

                    <path d="M350 210 C390 135, 440 110, 495 92" marker-end="url(#arrow)"/>
                    <path d="M575 115 C670 115, 720 115, 780 95" marker-end="url(#arrow)"/>
                    <path d="M815 120 C830 160, 830 185, 820 210" marker-end="url(#arrow)"/>

                    <path class="dash" d="M350 320 C350 365, 350 390, 350 405" marker-end="url(#arrow)"/>
                    <path class="dash" d="M440 410 H500" marker-end="url(#arrow)"/>
                    <path class="dash" d="M660 410 H720" marker-end="url(#arrow)"/>
                    <path class="dash" d="M815 390 C815 340, 815 325, 815 315" marker-end="url(#arrow)"/>
                </svg>

                @if($workspace)
                <a class="node workspace active" href="{{ $workspace }}">
                @else
                <div class="node workspace">
                @endif
                    <div class="topline"><span class="n">01 · Cadrer</span><span class="state"></span></div>
                    <strong>Workspace</strong>
                    <p>Structure du projet, lots, activités et documents.</p>
                    @if($workspace)<div class="open">OUVRIR →</div>@endif
                @if($workspace)</a>@else</div>@endif

                <div class="node project active">
                    <div class="topline"><span class="n">Noyau</span><span class="state"></span></div>
                    <strong>Projet pilote Civilina Engineering</strong>
                    <p>Point de convergence entre structure, exigences, contrôles et preuves.</p>
                </div>

                @if($library)
                <a class="node normes active" href="{{ $library }}">
                @else
                <div class="node normes">
                @endif
                    <div class="topline"><span class="n">02 · Référencer</span><span class="state"></span></div>
                    <strong>Normes applicables</strong>
                    <p>Références, éditions, sections et exigences.</p>
                    @if($library)<div class="open">OUVRIR →</div>@endif
                @if($library)</a>@else</div>@endif

                @if($itp)
                <a class="node itp active" href="{{ $itp }}">
                @else
                <div class="node itp">
                @endif
                    <div class="topline"><span class="n">03 · Planifier</span><span class="state"></span></div>
                    <strong>ITP</strong>
                    <p>Inspections, essais, points d’arrêt et acceptation.</p>
                    @if($itp)<div class="open">OUVRIR →</div>@endif
                @if($itp)</a>@else</div>@endif

                @if($controls)
                <a class="node controls active" href="{{ $controls }}">
                @else
                <div class="node controls">
                @endif
                    <div class="topline"><span class="n">04 · Contrôler</span><span class="state"></span></div>
                    <strong>Contrôles & PV</strong>
                    <p>Inspections, résultats d’essais et preuves terrain.</p>
                    @if($controls)<div class="open">OUVRIR →</div>@endif
                @if($controls)</a>@else</div>@endif

                @if($links)
                <a class="node trace active" href="{{ $links }}">
                @else
                <div class="node trace">
                @endif
                    <div class="topline"><span class="n">05 · Justifier</span><span class="state"></span></div>
                    <strong>Traçabilité normative</strong>
                    <p>Contrôle ↔ norme ↔ édition ↔ exigence.</p>
                    @if($links)<div class="open">OUVRIR →</div>@endif
                @if($links)</a>@else</div>@endif

                <div class="node dossier output">
                    <div class="topline"><span class="n">06 · Consolider</span><span class="state"></span></div>
                    <strong>Dossier d’ingénierie</strong>
                    <p>Structure, normes, ITP, PV, essais, preuves et historique.</p>
                    <div class="open">SORTIE DU SYSTÈME</div>
                </div>

                @if($librarian)
                <a class="node librarian foundation active" href="{{ $librarian }}">
                @else
                <div class="node librarian foundation">
                @endif
                    <div class="topline"><span class="n">Fondation A</span><span class="state"></span></div>
                    <strong>Normative Librarian</strong>
                    <p>Collecter, identifier et classer les documents.</p>
                    @if($librarian)<div class="open">OUVRIR →</div>@endif
                @if($librarian)</a>@else</div>@endif

                @if($review)
                <a class="node review foundation active" href="{{ $review }}">
                @else
                <div class="node review foundation">
                @endif
                    <div class="topline"><span class="n">Fondation B</span><span class="state"></span></div>
                    <strong>Revue canonique</strong>
                    <p>Stabiliser la référence et la bonne édition.</p>
                    @if($review)<div class="open">OUVRIR →</div>@endif
                @if($review)</a>@else</div>@endif

                @if($library)
                <a class="node library foundation active" href="{{ $library }}">
                @else
                <div class="node library foundation">
                @endif
                    <div class="topline"><span class="n">Fondation C</span><span class="state"></span></div>
                    <strong>Bibliothèque normative</strong>
                    <p>Mettre les référentiels à disposition du projet.</p>
                    @if($library)<div class="open">OUVRIR →</div>@endif
                @if($library)</a>@else</div>@endif
            </div>
        </div>

        <div class="flow-strip">
            <div class="flow-step"><b>01 · CADRER</b><span>Structure projet</span></div>
            <div class="flow-step"><b>02 · RÉFÉRENCER</b><span>Normes</span></div>
            <div class="flow-step"><b>03 · PLANIFIER</b><span>ITP</span></div>
            <div class="flow-step"><b>04 · CONTRÔLER</b><span>PV & essais</span></div>
            <div class="flow-step"><b>05 · JUSTIFIER</b><span>Traçabilité</span></div>
            <div class="flow-step"><b>06 · CONSOLIDER</b><span>Dossier final</span></div>
        </div>
    </section>

    <section class="section">
        <div class="section-title">
            <div>
                <h2>Lecture SaaS du système</h2>
                <p>Trois couches simples pour expliquer Civilina à un dirigeant ou à un nouvel utilisateur.</p>
            </div>
        </div>

        <div class="logic-grid">
            <div class="logic-card">
                <div class="label">Couche 1 · Données projet</div>
                <strong>Structurer avant de contrôler</strong>
                <p>Les lots, activités et documents donnent le contexte à toutes les opérations QA/QC.</p>
                <div class="mini">
                    <span>Projet</span><i>→</i><span>Lots</span><i>→</i><span>Activités</span>
                </div>
            </div>

            <div class="logic-card">
                <div class="label">Couche 2 · Moteur de conformité</div>
                <strong>Transformer la norme en contrôle</strong>
                <p>Le référentiel devient un ITP, puis une inspection mesurable et traçable.</p>
                <div class="mini">
                    <span>Norme</span><i>→</i><span>ITP</span><i>→</i><span>PV</span>
                </div>
            </div>

            <div class="logic-card">
                <div class="label">Couche 3 · Preuve</div>
                <strong>Construire le dossier au fil du projet</strong>
                <p>Les preuves sont consolidées dès leur création au lieu d’être recherchées en fin de chantier.</p>
                <div class="mini">
                    <span>PV</span><i>→</i><span>Preuves</span><i>→</i><span>Dossier</span>
                </div>
            </div>
        </div>

        <div class="note">
            <strong>Principe d’interface :</strong>
            chaque nœud du graphe explique une fonction métier et devient un point d’entrée SaaS.
            Le visuel n’est donc pas décoratif : il sert de carte du système Civilina Engineering.
        </div>
    </section>
</main>
</body>
</html>
