<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts the shared normative corpus (standards files, scans, indexing,
 * corpus-wide edits) to Civilina platform staff. Engineering firms keep
 * read access to the published registry through their own organization.
 *
 * Staff are listed by e-mail in CIVILINA_PLATFORM_STAFF (comma separated).
 * An empty list denies everyone (fail closed).
 */
class EnsureCivilinaPlatformStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(self::isStaff($request->user()?->email), 403);

        return $next($request);
    }

    public static function isStaff(?string $email): bool
    {
        $email = strtolower(trim((string) $email));

        if ($email === '') {
            return false;
        }

        $staff = array_map(
            static fn ($value): string => strtolower(trim((string) $value)),
            (array) config('civilina.platform_staff', [])
        );

        return in_array($email, array_filter($staff), true);
    }
}
