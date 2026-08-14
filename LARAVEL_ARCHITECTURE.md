# Laravel Architecture

## Web layer

- `routes/web.php`: one public `GET /` route.
- `resources/views/layouts/app.blade.php`: metadata, Vite and deterministic reasoning module order.
- `resources/views/home.blade.php`: validated Civilina homepage and interactive journey.
- `resources/css/app.css`: responsive interface.
- `resources/js/app.js`: Vite application entry.
- `resources/js/civilina-ai/`: the only official JavaScript reasoning source.

`vite.config.js` copies the CommonJS-compatible browser modules unchanged to `public/build/civilina-ai`, while Vite bundles the application entry and CSS.

## Domain layer

`app/Domain/CivilinaAI` contains DTOs, registries, recognition, facts, privacy, questions, recommendations and the reasoning orchestration. It has no dependency on controllers, sessions or a database.

## Runtime modes

`CIVILINA_REASONING_RUNTIME=client` is the only active web runtime. The PHP implementation is currently a parity-validated server foundation; no endpoint accepts free text in Sprint 2.2.

## Boundaries

No authentication, business persistence, document upload, LLM provider or production deployment is included.

