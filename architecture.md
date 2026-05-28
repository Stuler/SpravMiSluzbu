# Architecture

This document describes the current Bripeon architecture and the intended direction for local, test, and production environments.

## Product Shape

Bripeon is a demand portal and service marketplace:

- customers create or browse service demand workflows
- providers register, choose service categories and regions, and can enter a subscription/payment flow
- admins manage categories, providers, customers, advertisements/orders, and operational data

The project is a modular monolith. Keep it deployable as one PHP/Nette application.

## Runtime Stack

- PHP 8.2+
- Nette Framework 3 and Contributte packages
- Latte templates
- Doctrine ORM through Nettrine
- MySQL/MariaDB
- Nginx + PHP-FPM
- Webpack-built frontend bundles
- React/TypeScript only for isolated widgets
- Stripe integration for provider payment/subscription work

## Directory Map

```text
app/
  Console/              CLI commands
  Domain/               Entities, repositories, facades, domain workflows
  Infrastructure/       External integrations such as Stripe
  Model/                Framework glue, routing, mail, security, database helpers
  UI/                   Presenters, Latte templates, forms, controls
config/
  app/                  Parameters and DI services
  env/                  base/dev/test/prod config includes
  ext/                  extension configuration
db/
  Fixtures/             Doctrine fixtures
  ManualMigrations/     Historical/manual SQL migration files
  Migrations/           Doctrine migration classes
  dump/                 SQL dumps for local/test bootstrapping
www/
  assets_admin/         Admin assets
  assets_front/         Front assets
  bundle/               Compiled bundles
  index.php             Public entrypoint
js/react/               React/TypeScript provider form code
.docker/                Local Docker runtime
.docs/                  Deployment and operations documentation
```

## Application Layers

### UI Layer

`app/UI` owns HTTP and rendering concerns:

- presenters
- templates
- forms
- UI controls
- redirects
- request/response handling

Presenters should orchestrate, not contain business rules or SQL.

### Domain Layer

`app/Domain` owns application-specific concepts:

- users and roles
- providers
- category services
- regions/cities
- orders/advertisements
- subscription entities

Facades and services should hold workflows such as provider registration, activation, demand creation, and subscription setup.

### Infrastructure Layer

`app/Infrastructure` owns third-party integration details. Stripe code belongs here, not in presenters.

### Model Layer

`app/Model` contains framework glue and shared infrastructure:

- routing
- security/authentication helpers
- mail sender
- database decorators/query helpers
- Latte helpers
- utilities

## Persistence

The project currently uses Doctrine ORM through Nettrine. Entities live in `app/Domain`, and migrations live in `db/Migrations`.

Important rules:

- keep schema and entity mapping in sync
- do not run migrations automatically on Roští test deploy yet
- keep test and production databases separate
- keep environment-specific credentials in `config/local.neon`

The current dump for local bootstrap is:

```text
db/dump/2025_09_04.sql
```

## Frontend

The default frontend is server-rendered Nette + Latte.

Compiled assets are produced by Webpack:

```bash
npm install
npm run build
```

React/TypeScript exists for the provider sign-up/payment experience under:

```text
js/react/
www/assets_front/front.tsx
```

Do not expand React into a full SPA without an explicit decision.

## Local Environment

Local development uses Docker Compose:

```bash
docker compose up --build -d
```

Services:

- app: `http://localhost:8082`
- Adminer: `http://localhost:8081`
- MySQL from host: `127.0.0.1:3307`
- MySQL inside Docker: host `database`, DB `sprav_mi_sluzbu`, user `root`, password `root`

The PHP container copies `config/local.neon.example` to `config/local.neon` if missing. The database imports `db/dump/2025_09_04.sql` on first volume initialization.

Reset local DB:

```bash
docker compose down -v
docker compose up --build -d
```

Run Nette cache maintenance commands as the PHP-FPM user:

```bash
docker compose exec -u www-data php php bin/console nette:cache:purge
```

Running cache commands as root can leave `var/tmp/cache` unwritable for PHP-FPM.

## Test Environment

Branch:

```text
beta
```

Target:

```text
test.spravmisluzbu.sk
Roští app SSH: app@ssh.rosti.cz:10434
Deploy path: /srv/app
Public root: /srv/app/www
```

Deployment is handled by `.github/workflows/deploy.yml`:

- build dependencies
- create `config/local.neon` from `ROSTI_TEST_LOCAL_NEON_BASE64`
- decode `ROSTI_TEST_SSH_KEY_BASE64`
- rsync the app to Roští
- prepare `var/tmp` and `var/log`
- clear Nette config cache

The test deploy intentionally does not run database migrations.

Required GitHub Actions secrets:

```text
ROSTI_TEST_SSH_KEY_BASE64
ROSTI_TEST_LOCAL_NEON_BASE64
```

See:

```text
.docs/rosti-test-deploy.md
.docs/rosti-test-local.neon.example
.docs/rosti-test-nginx-app.conf
```

## Production Environment

Branch:

```text
main
```

Production deployment is currently configured through FTP secrets in `.github/workflows/deploy.yml`:

```text
PROD_FTP_SERVER
PROD_FTP_USERNAME
PROD_FTP_PASSWORD
PROD_FTP_SERVER_DIR
```

Production must use production-only:

- database
- `config/local.neon`
- Stripe keys
- SMTP credentials
- domain/HTTPS configuration

Do not reuse test credentials in production.

## Development Deployment

Branch:

```text
dev
```

Development deployment still uses FTP secrets:

```text
DEV_FTP_SERVER
DEV_FTP_USERNAME
DEV_FTP_PASSWORD
DEV_FTP_SERVER_DIR
```

Keep dev separate from beta/test and production.

## Known Technical Debt

- Some legacy skeleton naming remains in `composer.json` and templates.
- Provider subscription code needs a focused pass before payment behavior is production-ready.
- Doctrine entity mapping around subscription state needs cleanup.
- Migrations and imported dumps are not yet normalized into a clean environment strategy.
- Frontend assets contain both historical static assets and Webpack bundles.
- Deployment workflow is functional but should eventually be split into clearer per-environment jobs.

## Refactor Strategy

Refactor incrementally:

1. Stabilize deployment and environment configuration.
2. Fix provider registration and subscription model boundaries.
3. Introduce clear customer demand/order workflow naming.
4. Move business logic out of presenters into domain facades/services.
5. Keep Doctrine mapping and migrations coherent.
6. Clean legacy assets only after routes and templates are covered by smoke tests.

Avoid broad rewrites. Each refactor should have a small verification plan.
