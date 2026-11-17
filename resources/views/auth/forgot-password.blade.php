<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Mot de passe oublié — Civilina</title>
@include('auth._card-style')
@include('components.civilina-command-theme')
</head>
<body>
    <main class="login-shell">
        <h1 class="brand">CIVILINA</h1>

        <p class="subtitle">
            Indiquez l’adresse e-mail de votre compte : nous vous envoyons un lien pour choisir un nouveau mot de passe.
        </p>

        @if (session('status'))
            <div class="status" role="status">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="error" role="alert">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('password.email') }}">
            @csrf

            <div class="field">
                <label for="email">Adresse e-mail</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus>
            </div>

            <button type="submit">Recevoir le lien</button>
        </form>

        <p class="links"><a href="{{ route('login') }}">Retour à la connexion</a></p>
    </main>
</body>
</html>
