# Bripeon

Bripeon is a Slovak service marketplace and demand portal. It connects customers looking for services with providers who offer services by category and region.

## Stack

- PHP 8.2+
- Nette Framework 3 / Contributte
- Latte templates and Nette Forms
- Doctrine ORM through Nettrine
- MySQL/MariaDB
- Docker Compose with Nginx, PHP-FPM, MySQL, and Adminer
- Webpack, TypeScript, and focused React widgets
- Stripe integration for provider subscription/payment work

## Local Development

Start the full local environment:

```bash
docker compose up --build -d
```

Local URLs:

- Public site: `http://localhost:8082`
- Provider sign-up: `http://localhost:8082/provider-sign/up`
- API categories: `http://localhost:8082/api/categories`
- Adminer: `http://localhost:8081`
- MySQL from host: `127.0.0.1:3307`

Local database credentials:

```text
host: database
dbname: sprav_mi_sluzbu
user: root
password: root
port: 3306
```

The first database start imports:

```text
db/dump/2025_09_04.sql
```

Reset local database from the dump:

```bash
docker compose down -v
docker compose up --build -d
```

The PHP container copies `config/local.neon.example` to `config/local.neon` if the local file is missing.

## Frontend

```bash
npm install
npm run build
```

React/TypeScript is currently used for isolated provider sign-up/payment UI. The default site and admin remain Nette + Latte.

## Useful Commands

```bash
docker compose ps
docker compose logs -f
docker compose exec php php bin/console migrations:status
docker compose exec -u www-data php php bin/console nette:cache:purge
docker compose exec php php -l app/Settings.php
docker compose exec php vendor/bin/phpstan analyse -c phpstan.neon --memory-limit=512M
```

Run Nette cache commands as `www-data`. Running them as root can create `var/tmp/cache` files that PHP-FPM cannot rewrite.

## Environments

| Environment | Branch | Target | Deploy |
| --- | --- | --- | --- |
| Local | any | `localhost:8082` | Docker Compose |
| Development | `dev` | dev server | GitHub Actions FTP |
| Test | `beta` | `test.spravmisluzbu.sk` on Roští | GitHub Actions SSH/rsync |
| Production | `main` | production domain | GitHub Actions FTP |

Test deployment on Roští uses:

```text
app@ssh.rosti.cz:10434
/srv/app
/srv/app/www
```

Required test GitHub Actions secrets:

```text
ROSTI_TEST_SSH_KEY_BASE64
ROSTI_TEST_LOCAL_NEON_BASE64
```

The test deploy does not run database migrations automatically. Database changes are manual until the migration strategy is settled.

Production uses separate FTP, database, SMTP, and Stripe secrets. Do not reuse test secrets in production.

## Documentation

- [AGENTS.md](AGENTS.md): coding-agent and refactor guidance
- [architecture.md](architecture.md): architecture, environment, and deployment overview
- [.docs/rosti-test-deploy.md](.docs/rosti-test-deploy.md): Roští test deployment details
- [.docs/rosti-test-local.neon.example](.docs/rosti-test-local.neon.example): test `local.neon` template
- [.docs/rosti-test-nginx-app.conf](.docs/rosti-test-nginx-app.conf): Roští Nginx config

## Manual Installation Without Docker

Docker is the recommended local path. Manual setup is only for debugging:

```bash
composer install
npm install
cp config/local.neon.example config/local.neon
npm run build
php -S localhost:8000 -t www
```

Then open:

```text
http://localhost:8000
```
