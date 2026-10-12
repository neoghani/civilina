
<section class="panel" style="border-left:4px solid #5d4e9f">
    <div class="toolbar">
        <div>
            <h2 style="margin:0">Références manquantes & OCR</h2>
            <div class="muted">
                Récupère les références à partir du nom, chemin, titre et texte déjà extrait. Les PDF sans texte restent dans une file OCR séparée.
            </div>
        </div>
        <div class="actions">
            <form method="post" action="{{ route('engineering.normative-recovery.references',['organization'=>$organization,'project'=>$project]) }}">
                @csrf
                <input type="hidden" name="limit" value="950">
                <button class="btn primary">Récupérer les références manquantes</button>
            </form>
            <form method="post" action="{{ route('engineering.normative-recovery.ocr-queue',['organization'=>$organization,'project'=>$project]) }}">
                @csrf
                <button class="btn">Exporter file OCR</button>
            </form>
        </div>
    </div>
</section>
