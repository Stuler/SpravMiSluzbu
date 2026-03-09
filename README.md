# SpravMiSluzbu

SpravMiSluzbu is a PHP web application built on Nette and Contributte with Doctrine/Nettrine for persistence.

This repository is ready to run with Docker and ships with a preconfigured local development stack.

## Stack

- PHP 8.2
- Nette Framework 3
- Contributte ecosystem
- Doctrine ORM via Nettrine
- MariaDB 10.6
- Nginx
- Adminer
- Docker Compose

## Requirements

### Recommended

- Docker Desktop or Docker Engine
- Docker Compose v2
- Free local ports:
  - `8080` for the web app
  - `8081` for Adminer

### Optional local runtime without Docker

- PHP `>= 8.2`
- Composer
- MariaDB `10.6+`

## Quick Start

### 1. Start the stack

```bash
docker compose up -d --build
```

The first start can take a little longer because the PHP container seeds `config/local.neon` from `config/local.neon.example` when needed and runs `composer install` only when dependencies are missing or `composer.lock` changes.

### 2. Open the application

- App: [http://localhost:8080](http://localhost:8080)
- Admin area: [http://localhost:8080/admin](http://localhost:8080/admin)
- Adminer: [http://localhost:8081](http://localhost:8081)

### 3. Local config

- `config/local.neon` is the only runtime local config file the app reads.
- `config/local.neon.example` is the template for that file.
- On first Docker start, if `config/local.neon` is missing, the PHP container copies `config/local.neon.example` to `config/local.neon`.
- For non-Docker local development, copy `config/local.neon.example` to `config/local.neon` and adjust the values.

### 4. Adminer database login

Use these values in Adminer:

- System: `MySQL`
- Server: `database`
- Username: `root`
- Password: `root`
- Database: `sprav_mi_sluzbu`

## Database Notes

- On a fresh start, the database is initialized from `.docker/db/01_dump.sql`.
- The database is stored in a Docker named volume, so data persists between restarts.
- If you want a clean database re-import from the dump, run:

```bash
docker compose down -v
docker compose up -d --build
```

## Useful Commands

Start or rebuild:

```bash
docker compose up -d --build
```

See container status:

```bash
docker compose ps
```

Watch logs:

```bash
docker compose logs -f
docker compose logs -f php
docker compose logs -f nginx
```

Open a shell in the PHP container:

```bash
docker compose exec php sh
```

Restart PHP after config or bootstrap-related changes:

```bash
docker compose restart php
```

Stop the stack:

```bash
docker compose down
```

## Development Notes

- Docker mounts the project into the containers, so source code changes are reflected immediately.
- Docker does not use a separate Docker-only `local.neon`; it uses the same `config/local.neon` the app uses everywhere else.
- `config/local.neon.example` is the committed template, and `config/local.neon` stays ignored for machine-specific values.
- In development, mail is written to local files for inspection instead of being sent through a real mail server.
- There is no `package.json` in this repository, so there is currently no separate frontend build step required to start the app.

## Local Run Without Docker

Docker is the supported and easiest way to run the project. If you still want to run it locally, you will need your own:

- PHP 8.2+
- Composer install
- MariaDB setup
- `config/local.neon` configured for your machine

Then you can start the built-in PHP server:

```bash
php -S 0.0.0.0:8000 -t www
```

## Troubleshooting

1. Check container status:

```bash
docker compose ps
```

2. Check logs:

```bash
docker compose logs -f php
docker compose logs -f nginx
docker compose logs -f database
```

3. If config changes are not picked up, clear generated cache and restart PHP:

```bash
docker compose exec php sh -lc "rm -rf var/tmp/cache"
docker compose restart php
```
