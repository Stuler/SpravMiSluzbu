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

## Manual migration sequence

On a separately approved test environment, take a backup and apply the existing migration chain in order, including:

1. `Version20260618120000`: provider activation tokens.
2. `Version20261007120000`: customer tokens, session versions and demands.
3. `Version20261007130000`: provider availability, matching, AI runs and invitations.
4. `Version20261007140000`: independent administrative blocks, provider session versions and administrative audit.

The new migration moves legacy blocked states into the independent block flag. An existing activation timestamp restores the verification state to active; without that timestamp the account remains fresh. Every legacy blocked account stays blocked after migration. Review these conservative conversions before applying the migration. The migration has no automatic down path because verification and audit history must not be discarded.

No migration, test-environment deployment or production operation was performed as part of this implementation. Existing CI migration behaviour was not changed.

## Automated checks

The isolated suite uses a transient in-memory SQLite schema and a mail transport that renders templates and captures messages without SMTP. It never boots environment configuration or accesses the configured application database:

```sh
php tests/Cases/Integration/Customer/CustomerDemandTest.phpt
```

It includes `Admin/admin-checks.php`, covering real presenter/Latte rendering, permissions and identity separation, missing/forged CSRF, valid protected mutations, block/unblock session revocation, verification preservation, queued invitation cancellation, account link requests, coverage and category changes, retirement/history preservation, SQL pagination/filter combinations, root/child editing, rejected cycles and closed-demand mutations, and protected administrator login/logout. Existing customer/demand/matching scenarios continue to run in the same suite.

The isolated suite completed successfully on PHP 8.3.7, including real rendering of all new admin screens and protected administrator login/logout. PHP syntax checks also passed. Docker Compose configuration validation passed. No real email was sent.

## Remaining environment verification

Docker Desktop's Linux engine was unavailable and localhost:8082 was not serving the application. Docker Compose configuration was validated, but PHP checks and the isolated suite use the available PHP 8.3.7 runtime rather than Docker PHP 8.2. The runtime reports the existing duplicate Xdebug loading warning.

Before release, verify in the real Docker PHP 8.2 and target MySQL/MariaDB environment:

- Migration syntax and legacy block conversion using an approved test database backup.
- All admin routes and public/provider session revocation, including two concurrent category edits.
- Approved test email delivery and recovery, including the activation cooldown and ambiguous SMTP outcomes.
- Full flow: update provider -> create demand -> select providers -> send invitation -> respond -> review in administration.

Use a separate test domain, database, secrets and mail recipients. Apply migrations manually only after the test environment and migration operation are explicitly agreed. This implementation does not enable automatic migrations or production mail.
