# AGENTS.md

Guidance for coding agents working on this repository.

This file is the shared source of project instructions for Codex and Claude.
Keep `CLAUDE.md` as a short entry point rather than duplicating these rules.
Last reviewed against the local checkout: 2026-10-09. Implementation in this
checkout does not imply migration, deployment, or production readiness.

## Project Purpose

Bripeon is a Slovak service marketplace and demand portal. The product connects people who need household, property, garden, building, repair, cleaning, and related services with verified service providers.

Keep the system pragmatic, maintainable, and focused on real marketplace workflows:

- demand/request creation by customers
- provider registration and activation
- service category and location management
- customer accounts, guest demand access, and provider invitations
- provider subscriptions and payments as a future workflow
- admin review and operational support

Do not turn this into a generic CMS, a full SPA, or an over-engineered marketplace platform before the core demand-provider workflow is stable.

## Tech Stack

- PHP 8.2+
- Nette Framework 3 / Contributte ecosystem
- Latte templates
- Nette Forms
- Nette Security
- Doctrine ORM through Nettrine
- Doctrine migrations: automatic on beta deploy; manual in production
- MariaDB/MySQL
- Docker Compose with Nginx, PHP-FPM, MySQL, and Adminer
- Webpack
- TypeScript and React for isolated frontend widgets
- OpenAI Responses integration for asynchronous demand analysis and provider ranking

Subscription entities and historical sign-up/payment UI exist, but billing is not
an active workflow. `composer.json` currently has no Stripe PHP SDK dependency;
subscription tables are absent from the checked-in bootstrap dump/migrations.
Do not infer working payments from older README/architecture references to Stripe.

Do not introduce another PHP framework, another ORM, a full SPA rewrite, or a separate backend service by default.

## Architecture

Use the existing modular monolith structure:

- `app/UI`: presenters, templates, forms, UI components, HTTP/page concerns.
- `app/Console`: application CLI commands, including demand workers and AI evaluation.
- `app/Domain`: domain entities, repositories, facades, and feature workflows.
- `app/Infrastructure`: external AI integration and Doctrine repository implementations.
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
- `resources/prompts`: versioned demand analysis and provider ranking prompts.
- `tests/Cases`: unit and isolated integration tests using Nette Tester.

Keep presenters thin. Put database access in repositories/facades and business workflows in domain services/facades. Keep external APIs in `app/Infrastructure`.

## Domain Modules

Current domain areas are:

- Users and login roles
- Service categories
- Regions, districts, and cities
- Providers
- Provider regions
- Provider service categories
- Provider activation
- Customer accounts, activation, password recovery, and session revocation
- Guest/customer demands, verification, private access recovery, and closure
- Matching, AI run history, provider invitations, and invitation email delivery
- Legacy orders/advertisements (distinct from the current Demand workflow)
- Subscription and subscription plan models (inactive billing scaffold)
- Admin operations
- Mail and notifications

Do not force different workflows into generic abstractions too early. For example, provider registration, customer demand creation, and subscription billing are separate concepts.

`Job` and `Review`, public provider profiles, and admin review handling are present
in this checkout. Their migration was applied to local Docker on 2026-10-09;
shared-environment rollout must be verified separately. See
`.docs/verified-provider-reviews.md`. Do not apply application migrations merely
to run isolated tests.

## Workflow and Transaction Rules

Follow the project's DDD rules in [.docs/ddd-boundaries.md](.docs/ddd-boundaries.md)
when adding or changing domain behavior. Preserve module/aggregate boundaries,
domain invariants, repository contracts, and application transaction ownership.
Keep HTTP/form concerns in UI, business transitions in domain entities/services,
workflow coordination in facades, and persistence/external integrations in
infrastructure. A Doctrine relationship alone does not define an aggregate.
Use the existing domain vocabulary and incremental design; do not introduce generic
abstractions or a broad DDD rewrite without a concrete workflow need.

- Customer and provider identities are distinct; matching numeric IDs grant no cross-account access. Guest demands remain independent of later accounts with the same email.
- A demand reference alone grants no private access. Preserve scoped guest tokens, expiry, recovery rotation, and customer ownership checks.
- Matching uses registered, active, eligible providers and exact service-category coverage. AI output must pass backend ID validation and eligibility checks.
- A demand has a lifetime limit of three distinct provider invitations, including declined/cancelled invitations. Requeue must not reset that limit.
- Closed demands are terminal. Worker results require a current processing lease; stale results must not reopen or mutate a closed demand.
- Provider invitation accept/decline responses are final. Preserve contact visibility rules before and after acceptance.
- AI calls run outside database transactions. The outer application operation owns flush/commit/rollback; nested operations join it and cannot commit independently.
- Persist administrative audit records atomically with the mutation. Discard/reload mutated entities after rollback; do not reuse a manager closed by a database write failure.
- Send mail through after-commit callbacks where provided. These callbacks are not a durable outbox; failure cannot undo committed data.
- Ambiguous invitation email delivery must not retry automatically. Preserve the explicit administrative recovery workflow.
- The pending Job workflow stops further matching after provider selection. Demand closure alone does not establish completed work or review eligibility; preserve review authorization and moderation rules.

## Clean Code and Maintainability

- Follow the existing project conventions and `ruleset.xml`. Keep formatting changes scoped to touched code; do not reformat unrelated files.
- Use clear English names that reflect the domain and intent. Prefer explicit types and contracts where supported by PHP 8.2 and the existing framework.
- Keep methods and classes cohesive with one clear responsibility. Extract meaningful operations when complexity grows; avoid arbitrary size limits and unnecessary helper layers.
- Prefer simple control flow, guard clauses, and explicit state transitions over deeply nested conditions, boolean mode flags, or hidden side effects.
- Reduce duplication when the business meaning is shared. Do not merge different workflows merely because their code looks similar; apply SOLID principles pragmatically without speculative abstractions.
- Inject dependencies through the existing DI configuration. Avoid introducing service locators, global mutable state, or direct external-service construction inside domain workflows.
- Handle expected failures explicitly. Do not silently swallow exceptions or expose internal errors/secrets to users; preserve useful diagnostic context in safe logs.
- Write comments to explain business constraints or non-obvious decisions, rather than restating code. Remove dead code introduced or made obsolete by the change.
- Keep public contracts and existing behavior compatible unless the requested change requires otherwise. Limit refactoring to what helps the current task, and preserve unrelated local changes.
- Do not suppress static-analysis findings, weaken types, or add broad exception catches just to make checks pass. Fix the cause or document a specific, justified limitation.

## Test Design and Regression Coverage

- Add or update tests for new or changed business behavior, authorization rules, persistence constraints, and meaningful failure paths. For bug fixes, add a regression test that demonstrates the original defect when practical.
- Test observable outcomes and domain invariants, not private method structure or incidental call order. Tests should remain useful after an internal refactor.
- Use focused unit tests for pure domain transitions/validation and isolated integration tests for repository mapping, transactions, DI wiring, and workflows crossing layers. Reuse the existing Nette Tester conventions and fixtures.
- Cover relevant invalid input, ownership/permission denial, expiry boundaries, repeated submissions, and rollback behavior alongside the successful path. Include CSRF and escaping checks when changing protected forms or rendering user content.
- Keep tests deterministic and independent. Supply fixed times where needed, isolate database/filesystem state, capture mail, and fake AI/external APIs; do not depend on network calls, real credentials, or application environment configuration.
- Use dedicated synthetic fixtures with clear intent. Verify locking/concurrency claims against an explicitly isolated MySQL/MariaDB runtime when needed; SQLite success alone is insufficient evidence.
- Run the smallest relevant tests and quality checks before handing off code changes. Report what passed, what could not run, and any remaining environment verification; do not claim coverage from lint alone.
- Do not add tests for documentation-only changes, trivial reversible presentation edits, or tests that merely duplicate the implementation. Do not inflate scope to meet an arbitrary coverage percentage.

## Persistence Rules

- Use Doctrine ORM/Nettrine for current entity-backed domain work.
- Keep entity mapping explicit and consistent with the database schema.
- Do not add a second ORM.
- Presenters must not contain SQL.
- Keep schema changes in `db/Migrations` unless a manual production/test operation is explicitly requested.
- The bootstrap dump predates current customer/matching/admin and pending review migrations. Starting Docker from the dump does not provision the complete current schema.
- Keep raw SQL dumps or one-off import files in `db/dump` or `db/ManualMigrations`.
- Automatic migrations on the isolated Roští beta environment were explicitly approved on 2026-10-09. Production migrations still require a separately approved operation.
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
- automatic beta migrations approved on 2026-10-09; production migrations remain manual

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

This deletes the local database volume. Use only when the user has authorized a
local data reset, never as a routine verification step.

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

`migrations:status` reads the configured database; run it only against the intended
local/test environment. Historical `make build` and `make deploy` targets drop the
schema/load fixtures and are not safe substitutes for the current deployment workflow.

Local URLs:

- Public site: `http://localhost:8082`
- Provider sign-up: `http://localhost:8082/provider-sign/up`
- API categories: `http://localhost:8082/api/categories`
- Adminer: `http://localhost:8081`
- Database from host: `127.0.0.1:3307`

## Verification

For documentation-only changes, check the diff, links, and referenced paths;
application tests and running-container checks are unnecessary.

For application/runtime changes, run the smallest relevant checks:

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

### Isolated Tests and Static Analysis

Use PHP 8.2+ and Nette Tester, not PHPUnit. Select tests relevant to the change;
the following are existing entry points:

```bash
php tests/Cases/Unit/Domain/DomainTransitionsTest.phpt
php tests/Cases/Integration/DDD/ServiceWiringTest.phpt
php tests/Cases/Integration/Customer/CustomerDemandTest.phpt
php vendor/bin/phpstan analyse -c phpstan-ddd.neon --memory-limit=512M
```

The customer integration entry point includes matching, admin, DDD, and pending
review/UI checks. Its database is transient SQLite, AI is fake, and mail is captured.
Do not boot `App\Bootstrap` or load `config/local.neon` for isolated tests. PHP must
provide `pdo_sqlite`; the current Docker PHP image does not install it. Use an
isolated compatible runtime when necessary, and report deviations from Docker PHP 8.2.

`phpstan-ddd.neon` is a scoped level 6 check, not a replacement for repository-wide
level 9 analysis in `phpstan.neon`. Windows PHPStan may not resolve this WSL UNC
checkout; do not lower analysis requirements to hide runtime limitations.
SQLite tests do not establish MySQL row-lock behavior, migration validity, real
SMTP delivery, or live AI quality. Report these remaining rollout checks separately.

Demand workers can make paid AI calls and write invitations; `--send` also delivers
real email. `demands:evaluate --live` and admin AI preview make paid API calls.
These are operational actions requiring authorization, not routine test commands.
The evaluation command without `--live` makes no API calls. Do not start workers,
schedule cron, send real mail, or apply migrations as a side effect of verification.

## Project Documentation

Read the relevant feature document before changing its workflow:

- [Customer demands and matching](.docs/customer-demand-mvp.md): accounts, guest access, queue, AI configuration/prompts, worker commands, migrations, and isolated tests.
- [Admin MVP](.docs/admin-mvp.md): permissions, audit, account/catalogue operations, and manual migration sequence.
- [DDD boundaries](.docs/ddd-boundaries.md): aggregate invariants, locking, transaction ownership, and after-commit behavior.
- [DDD implementation series](.docs/ddd-pr1-4.md): completed refactor scope, repeatable checks, and remaining concurrency verification.
- [Verified provider reviews](.docs/verified-provider-reviews.md): pending Job/Review implementation, migration, pilot gates, and limitations.
- [Roští test deployment](.docs/rosti-test-deploy.md): beta configuration and operations.
- [README](README.md) and [architecture overview](architecture.md): general orientation; older billing/workflow descriptions need to be checked against current code and the feature documents above.

Keep workflow documentation aligned with code changes. Distinguish implemented
behavior, local verification, pending migrations, and actual environment rollout.

## Scope Guardrails

Do not implement these unless explicitly requested:

- a full SPA rewrite
- another ORM
- additional public account/registration workflows beyond the existing customer and provider flows
- production payment behavior without Stripe test coverage
- SMS notifications
- complex approval workflows beyond the pending verified-review moderation flow
- mobile app
- multilingual support
- comments/discussions
- automatic production migrations

## Known Refactor Direction

Future refactoring should move toward:

- clearer separation between presenters, facades, repositories, and infrastructure services
- stable provider sign-up and subscription domain model
- explicit demand/order/advertisement workflow naming
- consistent Doctrine entity relationships
- removal of legacy template/assets duplication where safe
- deployment documentation that matches actual environments

Keep refactors incremental and verified. Do not mix large domain refactors with deployment fixes unless the user explicitly asks for both in the same change.
