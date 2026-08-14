# CIVILINA Sprint 2.2

Expert Reasoning Core 2.0 and Laravel foundation.

## Runtime

- Laravel 12.64.0 on PHP 8.2+
- Blade homepage and Vite assets
- Civilina Reasoning Core 2.0 in the browser by default
- PHP reasoning services validated against the 180-case Golden Dataset V2
- no authentication, business database, upload, LLM or production deployment

## Local preview

```powershell
composer install
npm install
npm run build
php artisan serve
```

Open `http://127.0.0.1:8000`.

## Validation

```powershell
php artisan test
php artisan civilina:reasoning:parity
node qa/reasoning-v2-validation.mjs
node qa/expert-domain-validation.mjs
node qa/negative-validation.mjs
node qa/golden-v2-validation.mjs
```

The delivery archive intentionally excludes `.env`, `vendor`, `node_modules`, logs and caches.

