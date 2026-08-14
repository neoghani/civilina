# Laravel 13 Upgrade Gate

Sprint 2.2 uses Laravel 12.64.0 because the available runtime is PHP 8.2.12. Laravel 13 requires PHP 8.3 or newer.

Upgrade only after:

1. PHP 8.3+ is available in local, CI and hosting environments.
2. Composer dependencies are checked against Laravel 13.
3. The complete JS, PHP parity and browser QA suites pass unchanged.
4. The Laravel 13 request-forgery, cache/session prefix and package changes are reviewed.
5. A separate upgrade branch and rollback archive exist.

Do not change the global PHP runtime as part of this sprint.

