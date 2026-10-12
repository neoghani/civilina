<style>
    .civilina-session-nav {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        min-width: 0;
    }

    .civilina-session-user {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        min-width: 0;
        padding: 5px 9px 5px 6px;
        border: 1px solid #d7e1ea;
        border-radius: 9px;
        background: #f8fafc;
        color: #142338;
    }

    .civilina-session-avatar {
        display: inline-grid;
        place-items: center;
        width: 28px;
        height: 28px;
        flex: 0 0 28px;
        border-radius: 50%;
        background: #0d4b9f;
        color: #fff;
        font-size: 11px;
        font-weight: 800;
    }

    .civilina-session-copy {
        display: grid;
        min-width: 0;
        line-height: 1.12;
    }

    .civilina-session-label {
        color: #758295;
        font-size: 9px;
        font-weight: 700;
        letter-spacing: .06em;
        text-transform: uppercase;
    }

    .civilina-session-name {
        max-width: 170px;
        overflow: hidden;
        color: #142338;
        font-size: 12px;
        font-weight: 750;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .civilina-session-logout-form {
        display: inline-flex;
        margin: 0;
    }

    .civilina-session-logout {
        appearance: none;
        border: 1px solid #b8cbe0;
        border-radius: 9px;
        padding: 9px 11px;
        background: #fff;
        color: #0d4b9f;
        font: inherit;
        font-size: 12px;
        font-weight: 750;
        cursor: pointer;
    }

    .civilina-session-logout:hover {
        border-color: #0d4b9f;
        background: #f3f8fd;
    }

    @media (max-width: 900px) {
        .civilina-session-nav {
            width: 100%;
            align-items: stretch;
            flex-direction: column;
        }

        .civilina-session-user {
            width: 100%;
        }

        .civilina-session-name {
            max-width: none;
        }

        .civilina-session-logout-form,
        .civilina-session-logout {
            width: 100%;
        }

        .civilina-session-logout {
            text-align: center;
        }
    }
</style>

@auth
    @php
        $civilinaUser = auth()->user();

        $civilinaDisplayName = trim(
            (string) ($civilinaUser->name ?? '')
        );

        if ($civilinaDisplayName === '') {
            $civilinaDisplayName =
                (string) $civilinaUser->email;
        }

        $civilinaInitial =
            mb_strtoupper(
                mb_substr(
                    $civilinaDisplayName,
                    0,
                    1
                )
            );
    @endphp

    <div
        class="civilina-session-nav"
        data-civilina-authenticated-user
    >
        <div
            class="civilina-session-user"
            title="{{ $civilinaUser->email }}"
        >
            <span
                class="civilina-session-avatar"
                aria-hidden="true"
            >
                {{ $civilinaInitial }}
            </span>

            <span class="civilina-session-copy">
                <span class="civilina-session-label">
                    Connecté
                </span>

                <strong class="civilina-session-name">
                    {{ $civilinaDisplayName }}
                </strong>
            </span>
        </div>

        <form
            class="civilina-session-logout-form"
            method="POST"
            action="{{ url('/logout') }}"
        >
            @csrf

            <button
                class="civilina-session-logout"
                type="submit"
            >
                Déconnexion
            </button>
        </form>
    </div>
@else
    <a
        class="login-link"
        href="{{ url('/login') }}"
    >
        Connexion
    </a>
@endauth
