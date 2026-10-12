<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Civilina - Digitalisation, pilotage de projets et intelligence métier</title>
    <meta name="description" content="Civilina aide les entreprises à digitaliser leurs processus, piloter leurs projets et opérations, consolider leur performance et transformer leurs données en décisions.">
    <meta name="robots" content="index, follow">
    <meta name="theme-color" content="#ffffff">
    <link rel="canonical" href="https://civilina.com/">
    <link rel="icon" type="image/png" href="{{ asset('assets/brand/civilina-mark-2026.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <meta property="og:locale" content="fr_FR">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Civilina">
    <meta property="og:title" content="Civilina - Digitalisation, pilotage de projets et intelligence métier">
    <meta property="og:description" content="Civilina vous aide à comprendre, structurer, digitaliser et piloter votre organisation.">
    <meta property="og:url" content="https://civilina.com/">
    <meta property="og:image" content="https://civilina.com/assets/brand/civilina-engineering-signature-2026.png">
    <meta property="og:image:width" content="1536">
    <meta property="og:image:height" content="1024">
    <meta property="og:image:alt" content="Civilina Engineering - Structure, pilotage et digitalisation">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Civilina - Digitalisation, pilotage de projets et intelligence métier">
    <meta name="twitter:description" content="Digitalisation des processus, pilotage de projets et intelligence métier pour les entreprises.">
    <meta name="twitter:image" content="https://civilina.com/assets/brand/civilina-engineering-signature-2026.png">

    <script type="application/ld+json">
    {
        "@@context": "https://schema.org",
        "@type": "Organization",
        "name": "Civilina",
        "url": "https://civilina.com/",
        "logo": "https://civilina.com/assets/brand/civilina-mark-2026.png",
        "description": "Plateforme technologique B2B de digitalisation des processus et de pilotage des projets et opérations.",
        "sameAs": [
            "https://www.linkedin.com/company/civilina",
            "https://www.linkedin.com/in/abdelghani-bouhouche"
        ]
    }
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @foreach ([
        'civilina-ai-domains.js',
        'civilina-ai-rules.js',
        'civilina-ai-recommendations.js',
        'civilina-ai-privacy.js',
        'civilina-ai-reasoning.js',
        'civilina-ai-ontology.js',
        'civilina-ai-lexicon.js',
        'civilina-ai-recognizer.js',
        'civilina-ai-action-recognizer.js',
        'civilina-ai-artifact-recognizer.js',
        'civilina-ai-artifact-requirements.js',
        'civilina-ai-artifact-context.js',
        'civilina-ai-itp-builder.js',
        'civilina-ai-wbs-builder.js',
        'civilina-ai-risk-register-builder.js',
        'civilina-ai-risk-register-journey-bridge.js',
        'civilina-ai-raci-builder.js',
        'civilina-ai-raci-journey-bridge.js',
        'civilina-ai-workflow-builder.js',
        'civilina-ai-workflow-journey-bridge.js',
        'civilina-ai-dashboard-builder.js',
        'civilina-ai-dashboard-journey-bridge.js',  
        'civilina-ai-facts-v2.js',
        'civilina-ai-questions-v2.js',
        'civilina-ai-reasoning-v2.js',
        'civilina-ai-runtime.js',
        'civilina-ai-scenarios.js',
        'civilina-ai-artifact-persistence.js',
        'civilina-ai-journey.js',
    ] as $module)
        <script src="{{ asset('build/civilina-ai/'.$module) }}" defer></script>
    @endforeach
    <meta name="csrf-token" content="{{ csrf_token() }}">
@include('components.civilina-command-theme')
</head>
<body data-reasoning-runtime="{{ config('civilina.reasoning_runtime') }}">
    @yield('content')
</body>
</html>

