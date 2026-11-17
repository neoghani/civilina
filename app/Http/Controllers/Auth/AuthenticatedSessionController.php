<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(
        Request $request
    ): RedirectResponse {
        $credentials = $request->validate([
            'email' => [
                'required',
                'email',
            ],
            'password' => [
                'required',
                'string',
            ],
        ]);

        $remember =
            $request->boolean(
                'remember'
            );

        if (
            ! Auth::attempt(
                $credentials,
                $remember
            )
        ) {
            return back()
                ->withErrors([
                    'email' =>
                        'Identifiants invalides.',
                ])
                ->onlyInput(
                    'email'
                );
        }

        $request
            ->session()
            ->regenerate();

        // A page requested before login wins; otherwise members of a firm
        // that has a project go straight to their Engineering workspace.
        return redirect()
            ->intended(
                $this->hasEngineeringProject($request->user())
                    ? route('engineering.entry')
                    : '/'
            );
    }

    private function hasEngineeringProject(?\App\Models\User $user): bool
    {
        if (! $user) {
            return false;
        }

        return \App\Models\Project::query()
            ->whereIn(
                'organization_id',
                \App\Models\OrganizationMembership::query()
                    ->where('user_id', $user->getKey())
                    ->where('status', 'active')
                    ->select('organization_id')
            )
            ->exists();
    }

    public function destroy(
        Request $request
    ): RedirectResponse {
        Auth::guard('web')
            ->logout();

        $request
            ->session()
            ->invalidate();

        $request
            ->session()
            ->regenerateToken();

        return redirect(
            '/login'
        );
    }
}
