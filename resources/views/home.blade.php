@extends('layouts.app')

@section('content')
<a class="skip-link" href="#main-content">Aller au contenu principal</a>

    <header class="site-header">
        <div class="shell header-inner">
            <a class="brand" href="#top" aria-label="Civilina, retour en haut de la page">
                <img src="/assets/brand/civilina-mark-2026.png" alt="" width="38" height="38">
                <span class="brand-copy"><span class="brand-name">CIVILINA</span><span class="brand-sub">Intelligent Business Control</span></span>
            </a>
            <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="main-navigation" aria-label="Ouvrir le menu"><span></span><span></span><span></span></button>
            <nav class="main-nav" id="main-navigation" aria-label="Navigation principale">
                <div class="nav-links">
                    <a href="#platform">Plateforme</a>
                    <a href="#needs">Solutions</a>
                    <a href="#engineering">Engineering</a>
                    <a href="#insights">Ressources</a>
                </div>
                <div class="nav-actions">
                    <a class="login-link" href="#ai-command">Connexion</a>
                    <a class="btn btn-outline" href="mailto:abdelghani.bouhouche@civilina.com?subject=Demande%20de%20d%C3%A9monstration%20Civilina">Demander une démo</a>
                    <a class="btn btn-primary" href="#ai-command">Parler à Civilina AI</a>
                </div>
            </nav>
        </div>
    </header>

    <main id="main-content">
        <section class="hero moment-hero" id="top">
            <div class="shell hero-grid">
                <div class="hero-copy">
                    <span class="eyebrow">CIVILINA — INTELLIGENT BUSINESS CONTROL</span>
                    <h1>Digitalisez.<br>Pilotez. Décidez.</h1>
                    <p>Civilina vous aide à comprendre, structurer, digitaliser et piloter votre organisation.</p>
                    <div class="hero-actions">
                        <a class="btn btn-primary" href="#ai-command">Parler à Civilina AI</a>
                        <a class="btn btn-outline" href="#engineering">Voir Civilina Engineering</a>
                    </div>
                    <div class="hero-micro">AI + expertise métier + intelligence de pilotage</div>
                </div>

                <div class="command-center" id="ai-command" aria-label="Centre de commandement interactif Civilina AI">
                    <div class="command-top">
                        <div class="command-brand"><img src="/assets/brand/civilina-mark-2026.png" alt="" width="25" height="25"> CIVILINA AI</div>
                    </div>
                    <div class="command-body">
                        <div class="command-tools">
                            <span class="demo-label">Démonstration interactive</span>
                            <button class="ai-reset" id="ai-reset" type="button" hidden>Recommencer</button>
                        </div>
                        <div class="ai-progress" id="ai-progress" role="progressbar" aria-label="Progression du diagnostic" aria-valuemin="0" aria-valuemax="100" aria-valuenow="10">
                            <span class="is-active" data-progress="10">Comprendre</span>
                            <span data-progress="65">Analyser</span>
                            <span data-progress="80">Recommander</span>
                        </div>
                        <span class="visually-hidden" id="ai-phase">Comprendre</span>
                        <div class="ai-conversation" id="ai-conversation" role="log" aria-live="polite" aria-relevant="additions text"></div>
                        <div class="suggestion-grid" id="ai-suggestions" aria-label="Réponses suggérées"></div>
                        <form class="ai-form" id="ai-form">
                            <label class="visually-hidden" for="ai-need">Votre réponse à Civilina AI</label>
                            <textarea class="ai-input" id="ai-need" name="need" placeholder="Décrivez votre besoin…" autocomplete="off" maxlength="4000" rows="4"></textarea>
                            <button class="btn btn-navy" id="ai-submit" type="submit">Commencer</button>
                        </form>
                        <p class="form-note" id="form-note">Sans compte • Démonstration locale • Aucune donnée transmise</p>
                        <div class="journey-outcome" id="ai-outcome" tabindex="-1" aria-live="polite" hidden></div>
                    </div>
                </div>
            </div>
        </section>

        <section class="section section-dark moment-result" id="platform">
            <div class="shell">
                <div class="section-head">
                    <span class="eyebrow">CIVILINA CONTROL</span>
                    <h2>Une vue de direction. Toute l’entreprise derrière.</h2>
                    <p>Ce que le dirigeant obtient : une lecture consolidée des projets, des dérives et des décisions à préparer.</p>
                </div>
                <div class="result-layout">
                    <div class="control-screen" aria-label="Cockpit exécutif démonstratif">
                        <div class="control-top"><strong>EXECUTIVE PORTFOLIO HEALTH</strong><span>Données de démonstration</span></div>
                        <div class="kpi-row">
                            <div class="kpi"><span>Cost Forecast</span><strong>1,94 Md DZD</strong><small style="color:#e9b85f">+6,6 %</small></div>
                            <div class="kpi"><span>Schedule Forecast</span><strong>+18 jours</strong><small>Sous surveillance</small></div>
                            <div class="kpi"><span>Progress</span><strong>62 %</strong><small>Avancement global</small></div>
                            <div class="kpi"><span>Decisions Required</span><strong>3</strong><small>Cette semaine</small></div>
                        </div>
                        <div class="health-zone">
                            <div class="health-score"><div><strong>72</strong><span>Portfolio Health / 100</span></div></div>
                            <div class="health-bars">
                                <div class="health-line"><span>Maîtrisés</span><div class="health-track"><i style="width:78%;background:#16845b"></i></div><strong>7</strong></div>
                                <div class="health-line"><span>Surveillance</span><div class="health-track"><i style="width:42%;background:#c98616"></i></div><strong>3</strong></div>
                                <div class="health-line"><span>Critiques</span><div class="health-track"><i style="width:26%;background:#c84141"></i></div><strong>2</strong></div>
                            </div>
                        </div>
                    </div>
                    <aside class="decision-panel" aria-label="Décisions requises">
                        <div class="decision-head">Decisions Required</div>
                        <div class="decision danger"><strong>Projet Atlas</strong><span>Valider le plan de maîtrise coût</span></div>
                        <div class="decision warning"><strong>Projet Horizon</strong><span>Arbitrer le scénario délai</span></div>
                        <button class="ai-query" type="button" data-prompt="Pourquoi ce projet nécessite-t-il mon attention ?"><span>Civilina AI</span>Pourquoi ce projet nécessite-t-il mon attention&nbsp;?</button>
                    </aside>
                </div>
            </div>
        </section>

        <section class="section section-soft moment-needs" id="needs">
            <div class="shell">
                <div class="section-head"><span class="eyebrow">COMMENCEZ PAR VOTRE BESOIN</span><h2>Que souhaitez-vous améliorer&nbsp;?</h2><p>Choisissez une porte. Civilina AI prépare la conversation autour de votre priorité.</p></div>
                <div class="need-grid">
                    <button class="need-door" type="button" data-prompt="Je veux mieux piloter mes projets"><span class="need-id">PROJECTS</span><h3>Mieux piloter mes projets</h3><p>Délais, coûts, ressources, risques, portfolio.</p><span class="need-action">En parler à Civilina AI →</span></button>
                    <button class="need-door" type="button" data-prompt="Je veux structurer mon entreprise et clarifier les responsabilités"><span class="need-id">ORGANISATION</span><h3>Structurer mon entreprise</h3><p>Rôles, responsabilités, décisions, administration, processus.</p><span class="need-action">En parler à Civilina AI →</span></button>
                    <button class="need-door" type="button" data-prompt="Je veux améliorer mon système ISO et qualité"><span class="need-id">MANAGEMENT SYSTEMS</span><h3>Améliorer mon système ISO / qualité</h3><p>ISO, processus, audits, actions et amélioration continue.</p><span class="need-action">En parler à Civilina AI →</span></button>
                    <button class="need-door" type="button" data-prompt="Je veux digitaliser mes processus"><span class="need-id">DIGITALISATION</span><h3>Digitaliser mes processus</h3><p>Workflows, validations, documents, automatisations et dashboards.</p><span class="need-action">En parler à Civilina AI →</span></button>
                </div>
            </div>
        </section>

        <section class="section moment-method" id="method">
            <div class="shell">
                <div class="section-head"><span class="eyebrow">COMMENT CIVILINA TRAVAILLE</span><h2>Comprendre avant de digitaliser.</h2></div>
                <div class="method-line" aria-label="Méthode Civilina en quatre étapes">
                    <div class="method-step"><span>Understand</span><p>Comprendre l’entreprise et ses objectifs.</p></div>
                    <div class="method-step"><span>Structure</span><p>Organiser processus, rôles, données et règles.</p></div>
                    <div class="method-step"><span>Digitalize</span><p>Transformer le fonctionnement en workflows et applications.</p></div>
                    <div class="method-step"><span>Control</span><p>Piloter la performance et préparer les décisions.</p></div>
                </div>
                <div class="workspace">
                    <h3>Besoin de partager des informations sensibles&nbsp;?</h3>
                    <p>Civilina peut ouvrir un espace professionnel confidentiel et mettre en place un NDA avant l’échange de documents sensibles.</p>
                </div>
            </div>
        </section>

        <section class="section section-dark moment-engineering" id="engineering">
            <div class="shell engineering-layout">
                <div class="engineering-copy">
                    <span class="eyebrow">FIRST INDUSTRY SOLUTION</span>
                    <h2>Civilina Engineering</h2>
                    <p><strong>Engineering, Construction & EPC</strong></p>
                    <p>La première solution sectorielle Civilina, conçue autour de la réalité des projets complexes.</p>
                    <div class="engineering-action"><a class="btn btn-light" href="#engineering">Découvrir Civilina Engineering</a></div>
                </div>
                <div class="engineering-pillars">
                    <article class="pillar"><span>01</span><h3>Project Control</h3><p>Portfolio, WBS, planning, coûts, ressources, forecast.</p></article>
                    <article class="pillar"><span>02</span><h3>Engineering & Delivery</h3><p>Engineering, Procurement, Construction, documents et exécution.</p></article>
                    <article class="pillar"><span>03</span><h3>Assurance & Performance</h3><p>QA/QC, HSE, risques, conformité et performance.</p></article>
                </div>
            </div>
        </section>

        <section class="section moment-human" id="expertise">
            <div class="shell">
                <div class="human-layout">
                    <div class="human-copy">
                        <span class="eyebrow">AI + HUMAN EXPERTISE</span>
                        <h2>Intelligence augmentée par l’expérience terrain.</h2>
                        <p>Civilina AI prépare, structure et analyse. Lorsque votre situation exige un regard métier, des experts peuvent intervenir avec votre accord.</p>
                        <div class="human-note"><strong>AI intelligence. Human expertise when it matters.</strong><p>Management, ISO, Project Controls, Engineering, Construction, EPC, qualité et digitalisation.</p></div>
                    </div>
                    <div class="founder">
                        <img src="/assets/brand/abdelghani-bouhouche.jpg" alt="Abdelghani Bouhouche, fondateur de Civilina" width="150" height="150">
                        <div><strong>Abdelghani Bouhouche</strong><span>Expérience terrain en projets, qualité, Engineering et environnements EPC.</span></div>
                    </div>
                </div>

                <div class="insight-row" id="insights">
                    <article class="insight"><span>ISO 10006 • EPC</span><h3>Construire un système projet pilotable</h3><p>Relier responsabilités, jalons, maîtrise documentaire et indicateurs de pilotage.</p><a class="text-link" href="#insights">Lire dans Civilina Insights</a></article>
                    <article class="insight"><span>Qualité • Management</span><h3>Structurer un système qualité réellement utile</h3><p>Faire de la qualité un cadre de responsabilités, de processus et de décision.</p><a class="text-link" href="#insights">Lire dans Civilina Insights</a></article>
                </div>

                <div class="closing">
                    <div><span class="eyebrow" style="color:#c8edfb">CIVILINA AI</span><h2>Parlez à Civilina.</h2><p>Commencez simplement par expliquer ce que vous souhaitez améliorer.</p></div>
                    <div class="closing-actions"><a class="btn btn-primary" href="#ai-command">Commencer avec Civilina AI</a><a class="btn btn-light" href="mailto:abdelghani.bouhouche@civilina.com?subject=%C3%89change%20avec%20un%20expert%20Civilina">Parler à un expert</a></div>
                </div>
            </div>
        </section>
    </main>

    <footer class="site-footer">
        <div class="shell footer-inner">
            <div class="footer-brand"><a class="brand" href="#top"><img src="/assets/brand/civilina-mark-2026.png" alt="" width="38" height="38"><span class="brand-copy"><span class="brand-name">CIVILINA</span><span class="brand-sub">Structure • Control • Intelligence</span></span></a><p>Civilina vous aide à comprendre, structurer, digitaliser et piloter votre organisation.</p></div>
            <nav class="footer-group" aria-label="Plateforme"><h3>Platform</h3><a href="#platform">Civilina Control</a><a href="#ai-command">Civilina AI</a><a href="#method">Confidential Workspace</a></nav>
            <nav class="footer-group" aria-label="Ressources"><h3>Resources</h3><a href="#insights">Journal</a><a href="#insights">Publications</a><a href="#insights">Civilina Insights</a></nav>
            <nav class="footer-group" aria-label="Entreprise"><h3>Company</h3><a href="#expertise">À propos</a><a href="mailto:abdelghani.bouhouche@civilina.com">Contact</a><a href="https://www.linkedin.com/company/civilina" target="_blank" rel="noopener noreferrer">LinkedIn</a></nav>
        </div>
        <div class="shell footer-bottom"><span>© <span id="current-year">2026</span> Civilina. Tous droits réservés.</span><span>Digitalisation • Pilotage • Engineering</span></div>
    </footer>
@endsection


