# AGENTS.md

Guidance for coding agents working on this repository.

## Project Purpose

Bripeon is a Slovak service marketplace and demand portal. The product connects people who need household, property, garden, building, repair, cleaning, and related services with verified service providers.

Keep the system pragmatic, maintainable, and focused on real marketplace workflows:

- demand/request creation by customers
- provider registration and activation
- service category and location management
- provider subscriptions and payments
- admin review and operational support

Do not turn this into a generic CMS, a full SPA, or an over-engineered marketplace platform before the core demand-provider workflow is stable.

## Tech Stack

- PHP 8.2+
- Nette Framework 3 / Contributte ecosystem
- Latte templates
- Nette Forms
- Nette Security
- Doctrine ORM through Nettrine
- Doctrine migrations, currently handled manually on shared environments
- MariaDB/MySQL
- Docker Compose with Nginx, PHP-FPM, MySQL, and Adminer
- Webpack
- TypeScript and React for isolated frontend widgets
- Stripe PHP SDK and Stripe JS for provider subscription/payment flow

Do not introduce another PHP framework, another ORM, a full SPA rewrite, or a separate backend service by default.

## Architecture

Use the existing modular monolith structure:

- `app/UI`: presenters, templates, forms, UI components, HTTP/page concerns.
- `app/Domain`: domain entities, repositories, facades, and feature workflows.
- `app/Infrastructure`: integrations with external services such as Stripe.
- `app/Model`: Nette/framework glue, routing, mail, security, database helpers, utilities, shared infrastructure.
- `db/Migrations`: Doctrine migrations.
- `db/ManualMigrations`: historical/manual SQL migration scripts.
- `db/Fixtures`: development fixtures.
- `db/dump`: database dumps used for local/test bootstrapping.
- `www`: public document root and static assets.
- `www/assets_front`, `www/assets_admin`, `js/react`: frontend source/assets.
- `www/bundle`: compiled frontend bundles.
- `.docker`: local Docker runtime configuration.
- `.docs`: deployment and operations notes.

Keep presenters thin. Put database access in repositories/facades and business workflows in domain services/facades. Keep external APIs in `app/Infrastructure`.

## Domain Modules

Current and planned domain areas are:

- Users and login roles
- Service categories
- Regions, districts, and cities
- Providers
- Provider regions
- Provider service categories
- Provider activation
- Customer demands/orders/advertisements
- Provider subscriptions and subscription plans
- Stripe payments
- Admin operations
- Mail and notifications

Do not force different workflows into generic abstractions too early. For example, provider registration, customer demand creation, and subscription billing are separate concepts.

## Persistence Rules

- Use Doctrine ORM/Nettrine for current entity-backed domain work.
- Keep entity mapping explicit and consistent with the database schema.
- Do not add a second ORM.
- Presenters must not contain SQL.
- Keep schema changes in `db/Migrations` unless a manual production/test operation is explicitly requested.
- Keep raw SQL dumps or one-off import files in `db/dump` or `db/ManualMigrations`.
- Do not run migrations automatically on Roští test deploy until the migration strategy is explicitly approved.
- Never point test code or test deploys at the production database.

## UI Rules

- Public pages should remain server-rendered Nette + Latte by default.
- Admin pages should remain Nette + Latte + Nette Forms by default.
- Use Slovak for public/customer/provider/admin UI labels.
- Use English for code, identifiers, comments, exceptions, logs, and documentation.
- Use JavaScript for progressive enhancements and focused widgets.
- React/TypeScript is allowed only for isolated, UX-heavy features such as the provider sign-up/payment flow.
- Do not convert the site or admin into a full React SPA without an explicit product decision.

## Security Rules

- Protect admin routes with authentication and server-side permission checks.
- Do not rely on frontend hiding for permissions.
- Use Nette form CSRF protection.
- Validate uploads by MIME type, extension, and size before enabling upload features.
- Uploaded files must not be executable.
- Never commit real secrets in `.env`, `config/local.neon`, or documentation.
- Keep `config/local.neon` environment-specific and ignored by Git.
- Store Roští test secrets in GitHub Actions secrets.
- Keep Stripe test and production keys separated.

## Environment Rules

- `dev`: development branch and development deployment.
- `beta`: test environment on Roští, currently `test.spravmisluzbu.sk`.
- `main`: production deployment.

The test environment must remain separate from production:

- separate domain
- separate database
- separate secrets
- separate Stripe keys where possible
- no automatic migrations until approved

## Common Commands

Local Docker:

```bash
docker compose up --build -d
docker compose ps
docker compose logs -f
docker compose exec php php bin/console migrations:status
docker compose exec -u www-data php php bin/console nette:cache:purge
```

Run cache purge/warmup commands as `www-data` in Docker so PHP-FPM can rewrite generated cache files.

Local reset from dump:

```bash
docker compose down -v
docker compose up --build -d
```

Frontend:

```bash
npm install
npm run build
```

Quality checks:

```bash
docker compose exec php php -l app/Settings.php
docker compose exec php php bin/console migrations:status
docker compose exec php vendor/bin/phpstan analyse -c phpstan.neon --memory-limit=512M
```

Local URLs:

- Public site: `http://localhost:8082`
- Provider sign-up: `http://localhost:8082/provider-sign/up`
- API categories: `http://localhost:8082/api/categories`
- Adminer: `http://localhost:8081`
- Database from host: `127.0.0.1:3307`

## Verification

Before handing off changes, run the smallest relevant checks:

```bash
docker compose config
docker compose ps
curl -I http://localhost:8082
```

For PHP changes, lint touched files inside Docker PHP 8.2:

```bash
docker compose exec php php -l path/to/file.php
```

For routed UI changes, smoke-test affected URLs with `curl` or a browser. For deployment changes, validate YAML before pushing:

```bash
python3 - <<'PY'
import yaml
from pathlib import Path
with Path(".github/workflows/deploy.yml").open() as f:
    yaml.safe_load(f)
print("yaml ok")
PY
```

## Scope Guardrails

Do not implement these unless explicitly requested:

- a full SPA rewrite
- another ORM
- public user registration beyond the planned demand workflow
- production payment behavior without Stripe test coverage
- SMS notifications
- complex approval workflows
- mobile app
- multilingual support
- comments/discussions
- automatic production/test migrations

## Known Refactor Direction

Future refactoring should move toward:

- clearer separation between presenters, facades, repositories, and infrastructure services
- stable provider sign-up and subscription domain model
- explicit demand/order/advertisement workflow naming
- consistent Doctrine entity relationships
- removal of legacy template/assets duplication where safe
- deployment documentation that matches actual environments

Keep refactors incremental and verified. Do not mix large domain refactors with deployment fixes unless the user explicitly asks for both in the same change.
