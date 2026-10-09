# Admin MVP: implementation and verification

## Implemented scope

The administration uses Nette presenters, Latte and Nette Forms. Account changes are handled by `AdminAccountFacade`; filtered lists and dashboard counts by `AdminReadFacade`; important mutations are recorded in `admin_audit` through `AdminAuditFacade`. Only an authenticated, active, non-deleted administrator from the user table can access these services. Provider identities never unlock administration, even when their numeric ID matches a user.

- Providers: database pagination; company, ICO and email search; account, availability, category and region filters; profile/contact/address/service edits; coverage changes; blocking/unblocking; activation requests; paginated invitation and administrative history.
- Customers: database pagination; name and email search; account filters; name edits; blocking/unblocking; activation/password recovery requests; paginated account-owned demands and administrative history. Login emails are read-only and passwords are never shown or set by the administrator. Guest demands remain independent.
- Demands: database pagination; search; status, region, locality, inclusive date range and operational issue filters; links to customer and provider details. The landing dashboard links to the same predicates used for its counts. Waiting responses means an open invited demand with at least one delivered invitation awaiting a response; ambiguous delivery means an open demand with an unknown/sending invitation.
- Categories: root and child creation/editing including description and parent; cycle prevention; retirement through a confirmed POST form. Retirement includes every descendant, preserves existing rows and links, and removes the subtree from public options, provider registration, new demand validation, AI catalogues and provider matching. Retired categories may have their descriptive data edited but are not automatically reactivated. Hierarchy edits and retirement serialize through catalogue row locks.
- All mutations in the new administration, including login/logout, require CSRF-protected POST forms. Legacy category grid mutations have been removed in favour of the protected editor.

Administrative blocking is stored independently from email verification. Blocking and unblocking revoke sessions by incrementing the account's session version. Unblocking a fresh account leaves it fresh. New selections exclude blocked/paused providers; the delivery worker rechecks the provider before sending queued invitations. Invitations and their response history are retained when profiles or coverage change. The existing lifetime limit of three invitations, uniqueness and final closed demand state remain in force.

Audit records contain actor, timestamp, target, operation and explicit metadata such as changed category/region IDs. They contain no passwords, tokens or token-bearing links. Email audit operations record a request, not a guarantee of delivery; provider activation retains the existing five-minute cooldown and ambiguous delivery handling.

## Migration sequence

Apply the existing migration chain in order, including the versions below. Local migration execution requires authorization and a backup. Beta runs pending migrations during deployment; this strategy was explicitly approved on 2026-10-09. Production remains manual.

1. `Version20260618120000`: provider activation tokens.
2. `Version20261007120000`: customer tokens, session versions and demands.
3. `Version20261007130000`: provider availability, matching, AI runs and invitations.
4. `Version20261007140000`: independent administrative blocks, provider session versions and administrative audit.
5. `Version20261008140000`: jobs/reviews required by the current integrated checkout; see `verified-provider-reviews.md`.

The new migration moves legacy blocked states into the independent block flag. An existing activation timestamp restores the verification state to active; without that timestamp the account remains fresh. Every legacy blocked account stays blocked after migration. Review these conservative conversions before applying the migration. The migration has no automatic down path because verification and audit history must not be discarded.

Initial implementation did not apply migrations. On 2026-10-09, following explicit authorization, the four pending migrations from `Version20261007120000` through `Version20261008140000` were manually applied to local Docker database `sprav_mi_sluzbu` on MySQL 8.0.46. A private pre-migration dump was saved in the host's temporary directory as `bripeon-before-mvp-20261009.sql`. All 15 available migrations are recorded as executed. No beta/production deployment or migration was performed; CI migration behaviour was not changed.

## Automated checks

The isolated suite uses a transient in-memory SQLite schema and a mail transport that renders templates and captures messages without SMTP. It never boots environment configuration or accesses the configured application database:

```sh
php tests/Cases/Integration/Customer/CustomerDemandTest.phpt
```

It includes `Admin/admin-checks.php`, covering real presenter/Latte rendering, permissions and identity separation, missing/forged CSRF, valid protected mutations, block/unblock session revocation, verification preservation, queued invitation cancellation, account link requests, coverage and category changes, retirement/history preservation, SQL pagination/filter combinations, root/child editing, rejected cycles and closed-demand mutations, and protected administrator login/logout. Existing customer/demand/matching scenarios continue to run in the same suite.

The isolated suite completed successfully on PHP 8.3.7, including real rendering of all new admin screens and protected administrator login/logout. PHP syntax checks also passed. Docker Compose configuration validation passed. No real email was sent.

## Docker and MySQL verification, 2026-10-09

Checks now pass on Docker PHP 8.2.31 and MySQL 8.0.46:

- The complete customer/matching/admin/review/transaction integration suite passes on both SQLite and a disposable MySQL database, with synthetic data, fake AI and captured mail. Real presenters, forms and templates are exercised; no application account or credential is needed.
- Actual service configuration compiles in `ServiceWiringTest.phpt`; scoped PHPStan level 6 passes. This is not repository-wide level 9 coverage.
- `AdminMysqlTest.phpt` executes the actual admin migration against synthetic legacy tables. Both verified and unverified blocked accounts retain their block; active/fresh accounts remain unchanged. The application database had no legacy blocked rows, so this conversion required synthetic fixtures.
- Two independent PHP processes verify competing reciprocal category moves and retirement racing with a category move at MySQL REPEATABLE READ isolation. The test originally reproduced a cycle: ordinary ORM refresh reused an older snapshot after a locking read. Refresh now also uses a write lock, so validation sees current committed rows. Reciprocal moves are rejected and retirement includes the newly moved descendant.
- The full MySQL suite exposed unquoted reserved column names `use` in location entities and `interval` in the inactive subscription model. Explicit Doctrine quoting fixes writes without renaming columns or enabling billing.
- The existing Nginx container had no network attachment. Recreating only Nginx restored the local site without replacing the database volume. Public form/login routes return HTTP 200; unauthenticated protected admin routes redirect to admin login.
- `HttpWorkflowTest.phpt` starts an isolated loopback PHP HTTP server using actual application configuration plus explicit synthetic-database/file-mail overrides; it never loads `local.neon`. Real HTTP cookies and CSRF forms exercise administrator login, provider profile/coverage editing, guest demand creation, a captured email verification link, administrative provider selection, a captured invitation, provider login/acceptance and administrator inspection. Contact fields are hidden before acceptance and visible afterwards. This tests the manual-selection branch, not live AI or external SMTP. It is an HTTP regression test, not visual/browser-JavaScript coverage.

Repeatable opt-in checks inside the local project Docker environment:

```sh
docker compose exec -T php php tests/Cases/Integration/MySql/AdminMysqlTest.phpt --local-docker
docker compose exec -T php php tests/Cases/Integration/Customer/CustomerDemandTest.phpt --local-mysql-docker
docker compose exec -T php php tests/Cases/Integration/MySql/HttpWorkflowTest.phpt --local-docker
```

These commands use the explicit local Compose endpoint and development credentials, never `config/local.neon`. They create randomly named `bripeon_admin_test_*` / `bripeon_workflow_test_*` databases and delete only their own database afterwards. Do not run them on a shared or production Docker network. The default customer-suite command still uses SQLite. Application schema migration is separate from these synthetic tests.

## Remaining rollout verification

- Real SMTP delivery to designated test accounts, including provider activation cooldown, ambiguous delivery recovery, and the full flow through the browser with those accounts.
- Live AI quality evaluation remains separate from deterministic fake-AI workflow tests.
- Deployment-specific checks on beta/production, and MariaDB verification if that is the selected deployment engine. Local MySQL results do not establish MariaDB compatibility.
- Authenticated routes were exercised through both presenters and an isolated HTTP server with synthetic MySQL fixtures; the HTTP smoke check on the migrated application used anonymous requests only.

Use a separate test domain, database, secrets and mail recipients. Automatic migrations on beta are approved; see `rosti-test-deploy.md` for the existing deployment workflow. Production migrations and real email verification still require separately agreed operations.
