# Roští Test Deployment

Target test app:

- SSH: `app@ssh.rosti.cz:10434`
- Deploy path: `/srv/app`
- Suggested domain: `test.spravmisluzbu.sk`
- Git branch: `beta`

## DNS And Domain

The test subdomain is chosen by us. `test.spravmisluzbu.sk` is a good fit if it is not already used.

If DNS for `spravmisluzbu.sk` is not hosted by Roští, create these records for `test.spravmisluzbu.sk`:

```text
A     test    185.58.41.93
AAAA  test    2a01:430:144::2
```

Then add `test.spravmisluzbu.sk` to the Roští app domains in the app Parameters tab and enable HTTPS.

## GitHub Secrets

Required:

```text
ROSTI_TEST_SSH_KEY_BASE64=<base64 encoded private deploy key for app@ssh.rosti.cz:10434>
ROSTI_TEST_LOCAL_NEON_BASE64=<base64 encoded config/local.neon for test>
```

The workflow creates `/srv/app/config/local.neon` from `ROSTI_TEST_LOCAL_NEON_BASE64` before rsync upload. Do not keep the real test `local.neon` in Git. After deploying the `beta` branch, it runs pending Nettrine migrations and prints their status:

```bash
php bin/console migrations:migrate --no-interaction --allow-no-migration
php bin/console migrations:status --no-interaction
```

### Migration conventions

DBAL detects the actual database engine/version from the connection. Do not set
an unqualified `serverVersion: 10.0`: DBAL interprets it as MySQL, even when the
server is MariaDB, and migration metadata comparison can fail on every run.
If overriding the version in environment configuration, use the actual vendor
and version reported by that server.

- Store every schema change as a new migration in `db/Migrations` under the `Database\\Migrations` namespace.
- Generate migrations through Nettrine, then review and simplify the generated SQL before committing it:

  ```bash
  docker compose exec php php bin/console migrations:diff
  ```

- Give each migration a useful `getDescription()` value and implement a valid `down()` method where rollback is technically safe.
- Never edit a migration that has already run on a shared environment. Add a corrective migration instead.
- Keep deployed migrations backward-compatible with the previous application version because Roští deploys files in place. Use additive changes before destructive renames or column removals.
- Run the migration locally and verify the final status before merging to `beta`:

  ```bash
  docker compose exec php php bin/console migrations:migrate --no-interaction --allow-no-migration
  docker compose exec php php bin/console migrations:status
  ```

- Automatic migrations apply only to the isolated Roští test database. Production migrations remain manual until a separate production deployment and rollback strategy is approved.

### Legacy beta recovery, 2026-10-12

The first automatic MVP deployment stopped in `Version20240601121738` because
`forgotten_password` already existed. The beta database had an empty
`doctrine_migrations` table despite an existing legacy schema. Inspection also
found `Version20250214134949` only partially applied: the provider changes were
present, but six other `deleted_by` columns were still nullable VARCHAR without
their foreign keys. Merely marking all migrations as executed would hide these
missing changes and skip the new MVP schema.

After schema review and a private full backup in `/srv/backups/` outside the
deployment directory, the six missing changes were applied using
`db/ManualMigrations/20261012-rosti-beta-history.sql`. Each affected column
contained only NULL; the operation preserved all application row counts and
recorded only the ten verified historical versions through `Version20250214134949`.
The SQL file is a one-time recovery record, not an automatic migration or a
replayable bootstrap. Do not run it against another database. MySQL/MariaDB DDL
commits implicitly; a partial failure requires inspecting the completed statements
before resuming. Never use `migrations:version --add --all` as a deploy fix.

The remaining five migrations, starting at `Version20260618120000`, are handled
by the normal beta deployment. Confirm both the successful workflow result and
`migrations:status` before considering rollout complete.

The repair and all five subsequent migration SQL bodies pass on a disposable
local MySQL 8 database with synthetic data:

```bash
docker compose exec -T php php tests/Cases/Integration/MySql/LegacyMigrationRecoveryTest.phpt --local-docker
```

The test imports only CREATE TABLE statements from the bootstrap dump, never its
data, reproduces the partial legacy migration and empty history, and checks data
preservation and the resulting new tables. Actual DI configuration also passes
`ServiceWiringTest.phpt`. Read-only checks on beta MariaDB 11.4.10 confirmed that
automatic engine detection resolves the metadata error without altering its table.

The beta CLI currently runs PHP 8.5.5, while CI/local verification uses PHP 8.2.
Its Nette nullable-parameter deprecation and stale `opcache.so` loading directive
are separate runtime warnings, not the duplicate-table failure. Runtime alignment
and dependency upgrades remain separate maintenance work; vendor files were not edited.

Use `.docs/rosti-test-local.neon.example` as the starting point:

```bash
cp .docs/rosti-test-local.neon.example /tmp/rosti-test-local.neon
nano /tmp/rosti-test-local.neon
base64 -w 0 /tmp/rosti-test-local.neon
```

Optional, already defaulted in the workflow:

```text
ROSTI_TEST_SSH_HOST=ssh.rosti.cz
ROSTI_TEST_SSH_PORT=10434
ROSTI_TEST_DEPLOY_PATH=/srv/app
```

These values are hardcoded from `ssh://app@ssh.rosti.cz:10434` to avoid malformed GitHub secrets breaking `ssh-keyscan`.

Create the deploy key locally:

```bash
ssh-keygen -t ed25519 -C "github-actions-spravmisluzbu-test" -f ~/.ssh/spravmisluzbu_rosti_test
cat ~/.ssh/spravmisluzbu_rosti_test.pub | ssh -p 10434 app@ssh.rosti.cz 'mkdir -p /srv/.ssh && cat >> /srv/.ssh/authorized_keys && chmod 700 /srv/.ssh && chmod 600 /srv/.ssh/authorized_keys'
base64 -w 0 ~/.ssh/spravmisluzbu_rosti_test
```

Generate the config secret:

```bash
base64 -w 0 config/local.neon
```

## Roští Nginx

Set `/srv/conf/nginx.d/app.conf` on Roští to serve the Nette public directory. The prepared file is `.docs/rosti-test-nginx-app.conf`.

Upload, validate, and restart:

```bash
bash .docs/rosti-test-upload-nginx.sh
```

## Nette Proxy

Roští runs behind a reverse proxy. Add this to the test `local.neon` or environment config:

```neon
http:
    proxy: 127.0.0.1
```
