<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <meta name="referrer" content="no-referrer">
    <title>{{ $mode === 'invitation' ? 'Activer votre compte' : 'Nouveau mot de passe' }} — Civilina</title>
@include('auth._card-style')
@include('components.civilina-command-theme')
</head>
<body>
    <main class="login-shell">
        <h1 class="brand">CIVILINA</h1>

        <p class="subtitle">
            @if ($mode === 'invitation')
                Bienvenue dans CIVILINA Engineering. Choisissez votre mot de passe pour activer votre compte.
            @else
                Choisissez un nouveau mot de passe pour votre compte.
            @endif
        </p>

        @if ($errors->any())
            <div class="error" role="alert">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ $mode === 'invitation' ? route('invitation.store') : route('password.update') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <div class="field">
                <label for="email">Adresse e-mail</label>
                <input id="email" name="email" type="email" value="{{ old('email', $email) }}" autocomplete="username" required>
            </div>

            <div class="field">
                <label for="password">Mot de passe</label>
                <input id="password" name="password" type="password" autocomplete="new-password" minlength="12" required autofocus>
                <p class="hint">12 caractères minimum. Une phrase de passe est plus simple à retenir.</p>
            </div>

            <div class="field">
                <label for="password_confirmation">Confirmer le mot de passe</label>
                <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="12" required>
            </div>

            <button type="submit">{{ $mode === 'invitation' ? 'Activer mon compte' : 'Enregistrer le mot de passe' }}</button>
        </form>

        <p class="links"><a href="{{ route('login') }}">Retour à la connexion</a></p>
    </main>
</body>
</html>
