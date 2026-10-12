
<section class="panel" style="border-left:4px solid #102f46">
    <div class="toolbar">
        <div>
            <h2 style="margin:0">Dossier de normes classées</h2>
            <div class="muted">
                D:\Civilina_Normes_Classees · miroir physique sans déplacement des originaux.
            </div>
        </div>
        <div class="actions">
            <form method="post" action="{{ route('engineering.normative-physical-library.build',['organization'=>$organization,'project'=>$project]) }}">
                @csrf
                <input type="hidden" name="limit" value="250">
                <button class="btn primary">Mettre à jour 250 fichiers</button>
            </form>
        </div>
    </div>
</section>
