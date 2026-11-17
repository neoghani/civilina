<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >
    <title>Connexion — Civilina</title>

    <style>
        :root {
            color-scheme: light;
            font-family:
                Inter,
                ui-sans-serif,
                system-ui,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            background: #f5f7fa;
            color: #162234;
        }

        .login-shell {
            width: min(92vw, 430px);
            background: #fff;
            border: 1px solid #dce3eb;
            border-radius: 18px;
            padding: 32px;
            box-shadow:
                0 18px 48px
                rgba(22, 34, 52, 0.08);
        }

        .brand {
            margin: 0 0 6px;
            font-size: 1.65rem;
            letter-spacing: 0.04em;
            font-weight: 800;
        }

        .subtitle {
            margin: 0 0 28px;
            color: #607083;
            line-height: 1.5;
        }

        label {
            display: block;
            margin-bottom: 7px;
            font-size: 0.9rem;
            font-weight: 700;
        }

        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 12px 13px;
            border: 1px solid #bfcbd8;
            border-radius: 10px;
            font: inherit;
            color: inherit;
            background: #fff;
        }

        input:focus {
            outline: 3px solid rgba(25, 75, 123, 0.14);
            border-color: #194b7b;
        }

        .field {
            margin-bottom: 18px;
        }

        .remember {
            display: flex;
            align-items: center;
            gap: 9px;
            margin: 2px 0 22px;
            color: #43546a;
            font-size: 0.92rem;
        }

        .remember label {
            margin: 0;
            font-weight: 500;
        }

        button {
            width: 100%;
            border: 0;
            border-radius: 10px;
            padding: 13px 16px;
            background: #163f67;
            color: #fff;
            font: inherit;
            font-weight: 750;
            cursor: pointer;
        }

        button:hover {
            background: #0f3457;
        }

        .error {
            margin: -6px 0 18px;
            padding: 10px 12px;
            border: 1px solid #efc7c7;
            border-radius: 9px;
            background: #fff6f6;
            color: #8b2020;
            font-size: 0.9rem;
        }
    </style>
@include('components.civilina-command-theme')
</head>
<body>
    <main class="login-shell">
        <h1 class="brand">CIVILINA</h1>

        <p class="subtitle">
            Accès sécurisé à votre environnement de pilotage.
        </p>

        @if (session('status'))
            <div
                class="error"
                role="status"
                style="border-color:#c4e3cf;background:#f3fbf6;color:#1d5a35"
            >
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div
                class="error"
                role="alert"
            >
                {{ $errors->first() }}
            </div>
        @endif

        <form
            method="POST"
            action="{{ route('login.store') }}"
        >
            @csrf

            <div class="field">
                <label for="email">
                    Adresse e-mail
                </label>

                <input
                    id="email"
                    name="email"
                    type="email"
                    value="{{ old('email') }}"
                    autocomplete="email"
                    required
                    autofocus
                >
            </div>

            <div class="field">
                <label for="password">
                    Mot de passe
                </label>

                <input
                    id="password"
                    name="password"
                    type="password"
                    autocomplete="current-password"
                    required
                >
            </div>

            <div class="remember">
                <input
                    id="remember"
                    name="remember"
                    type="checkbox"
                    value="1"
                >

                <label for="remember">
                    Maintenir la session
                </label>
            </div>

            <button type="submit">
                Se connecter
            </button>
        </form>

        <p style="margin:18px 0 0;text-align:center;font-size:0.9rem">
            <a href="{{ route('password.request') }}" style="color:#163f67">
                Mot de passe oublié ?
            </a>
        </p>
    </main>
</body>
</html>
