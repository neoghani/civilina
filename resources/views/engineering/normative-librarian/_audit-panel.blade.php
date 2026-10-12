
<section class="panel" style="border-left:4px solid #a96400">
    <div class="toolbar">
        <div>
            <h2 style="margin:0">Audit de précision</h2>
            <div class="muted">
                Vérifie les AUTO_CLASSIFIED avant publication. Un mot générique seul ne suffit plus.
            </div>
        </div>
        <div class="actions">
            <form method="post" action="{{ route('engineering.normative-librarian.audit',['organization'=>$organization,'project'=>$project]) }}">
                @csrf
                <input type="hidden" name="limit" value="500">
                <input type="hidden" name="status" value="AUTO_CLASSIFIED">
                <button class="btn primary">Auditer 500 classés</button>
            </form>
            <a class="btn" href="?special=risk">Voir les classifications à risque</a>
        </div>
    </div>
</section>
