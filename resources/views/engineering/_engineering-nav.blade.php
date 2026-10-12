@if(isset($organization) && isset($project))
<style>
.civ-eng-nav{
    position:sticky;
    top:0;
    z-index:900;
    min-height:44px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
    padding:6px 14px;
    background:rgba(255,255,255,.97);
    backdrop-filter:blur(9px);
    -webkit-backdrop-filter:blur(9px);
    border-bottom:1px solid #dce3e8;
    color:#102235;
}
.civ-eng-nav__left,
.civ-eng-nav__right{
    display:flex;
    align-items:center;
    gap:7px;
    min-width:0;
}
.civ-eng-nav__link{
    display:inline-flex;
    align-items:center;
    gap:6px;
    min-height:30px;
    padding:4px 8px;
    border:1px solid #dce3e8;
    border-radius:7px;
    background:#fff;
    color:#16354a;
    text-decoration:none;
    font-size:11px;
    font-weight:720;
    white-space:nowrap;
}
.civ-eng-nav__link:hover{background:#f7f9fa;border-color:#a8b7c1}
.civ-eng-nav__brand{
    border-color:transparent;
    background:transparent;
    padding-left:2px;
    font-weight:850;
    letter-spacing:.02em;
}
.civ-eng-nav__sep{color:#a0adb6;font-size:11px}
.civ-eng-nav__project{
    color:#687b8c;
    font-size:11px;
    overflow:hidden;
    text-overflow:ellipsis;
    white-space:nowrap;
    max-width:300px;
}
@media(max-width:760px){
    .civ-eng-nav{padding:5px 8px}
    .civ-eng-nav__label-optional{display:none}
    .civ-eng-nav__project{max-width:38vw}
}
@media(max-width:480px){
    .civ-eng-nav__workspace{display:none}
    .civ-eng-nav__project{max-width:46vw}
}
</style>

<nav class="civ-eng-nav" aria-label="Navigation Civilina Engineering">
    <div class="civ-eng-nav__left">
        <a
            class="civ-eng-nav__link civ-eng-nav__brand"
            href="{{ route('civilina.home.bridge',['organization'=>$organization,'project'=>$project]) }}"
            title="Accueil Civilina"
        >
            CIVILINA
        </a>

        <span class="civ-eng-nav__sep">›</span>

        <a
            class="civ-eng-nav__link"
            href="{{ route('engineering.home.bridge',['organization'=>$organization,'project'=>$project]) }}"
            title="Accueil Ingénierie"
        >
            <span aria-hidden="true">←</span>
            <span>Ingénierie</span>
        </a>

        <span class="civ-eng-nav__sep civ-eng-nav__label-optional">›</span>

        <span class="civ-eng-nav__project">
            {{ $project->name ?? $project->title ?? ('Projet #'.$project->getKey()) }}
        </span>
    </div>

    <div class="civ-eng-nav__right">
        @if(\Illuminate\Support\Facades\Route::has('engineering.workspace.bridge'))
            <a
                class="civ-eng-nav__link civ-eng-nav__workspace"
                href="{{ route('engineering.workspace.bridge',['organization'=>$organization,'project'=>$project]) }}"
            >
                Workspace
            </a>
        @endif
    </div>
</nav>
@endif
