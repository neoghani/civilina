<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

/**
 * Password set-up for invited accounts (broker "invitations", long-lived
 * link) and password reset for existing accounts (broker "users", 60 min).
 * Civilina never handles the user's password: the person chooses it here.
 */
class PasswordSetupController extends Controller
{
    public function requestForm(): View
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        Password::broker('users')->sendResetLink([
            'email' => Str::lower(trim($validated['email'])),
        ]);

        // Same answer whether or not the account exists (no enumeration).
        return back()->with(
            'status',
            'Si un compte correspond à cette adresse, un lien de réinitialisation vient d’être envoyé. Il est valable 60 minutes.'
        );
    }

    public function resetForm(Request $request, string $token): View
    {
        return $this->form($request, $token, 'reset');
    }

    public function invitationForm(Request $request, string $token): View
    {
        return $this->form($request, $token, 'invitation');
    }

    public function reset(Request $request): RedirectResponse
    {
        return $this->store($request, 'users');
    }

    public function acceptInvitation(Request $request): RedirectResponse
    {
        return $this->store($request, 'invitations');
    }

    private function form(Request $request, string $token, string $mode): View
    {
        return view('auth.password-setup', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
            'mode' => $mode,
        ]);
    }

    private function store(Request $request, string $broker): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'confirmed', PasswordRule::min(12)],
        ]);

        $status = Password::broker($broker)->reset(
            [
                'email' => Str::lower(trim($validated['email'])),
                'token' => $validated['token'],
                'password' => $validated['password'],
                'password_confirmation' => $request->input('password_confirmation'),
            ],
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()
                ->withErrors(['email' => 'Ce lien n’est plus valable. Demandez un nouveau lien.'])
                ->onlyInput('email');
        }

        return redirect()
            ->route('login')
            ->with('status', 'Mot de passe enregistré. Vous pouvez vous connecter.');
    }
}
