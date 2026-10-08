# DDD implementation series: steps 1–4

The series builds on the existing provider/customer/demand/admin implementation
saved in baseline commit `bc943f8`, rather than replacing those workflows.
The four implementation steps are separate commits on `codex/ddd-pr1-4`.

## Implemented

1. Documented aggregate boundaries, state transitions, the lifetime invitation
   limit, repeated-request behavior and transaction ownership in
   `ddd-boundaries.md`. Added an entity characterization test.
2. Replaced the invalid subscription enum mapping with the existing
   `StateSubscription` entity relationship. Construction now requires an explicit
   initial state, initializes timestamps and the inverse collection, and has a
   tested persistence round trip. Subscription tables are absent from checked-in
   dumps/migrations; provisioning them remains a separate billing rollout task.
3. Added backed enums without changing stored demand/invitation values. Entities
   expose explicit matching/delivery outcomes, validate worker claims and support
   supplied immutable times. Provider activation updates state, date and token
   together; profile validation is shared through `ProviderProfile`, and repeated
   administrative blocking is idempotent.
4. Added domain repository contracts with Doctrine implementations and DI wiring.
   Demand/activation/delivery claim queries and locks live in persistence.
   Activation/resend revalidate under provider locks. Provider email insertion
   conflicts are translated after checking the actual email conflict; unrelated
   constraint failures propagate. Application transactions now join one owner,
   remain rollback-only after a caught inner error, and discard changed ORM state
   on rollback. Audit and changes commit together. Email callbacks wait for the
   outer application commit and are discarded on rollback.

UI labels and route behavior remain compatible. Full matching decomposition,
typed UI commands, identity/presentation extraction and query optimizations are
the subsequent steps 5–7, not part of this series.

## Verification

Verification used local PHP 8.3.7 because Docker Desktop was unavailable. The
ordinary non-interactive PHP/WSL runtime is 8.1 and cannot run this application.
The existing `Cannot load Xdebug - it was already loaded` warning occurred, but
the selected tests returned exit code 0.

- Eight selected test entrypoints passed: workflow baseline, domain transitions,
  subscription model/mapping, activation token, registration data, registration
  form, actual service configuration compilation, and the isolated customer suite.
- The customer suite also runs matching, invitation, admin and real presenter/
  Latte/CSRF checks, subscription persistence, nested transaction/audit rollback,
  after-commit failure, activation/resend and simulated concurrent email conflict.
- PHP syntax checks passed for 54 files in the affected areas.
- Targeted PHPStan level 6 analysis of model/repository/transaction code passed
  with `phpstan-ddd.neon`. This is an additional isolated check, not a replacement
  for the repository-wide level 9 configuration.
- `docker compose config --quiet` validates configuration. Running-container and
  localhost HTTP checks require Docker Desktop; they could not run here.

All integration databases are transient SQLite instances. Tests and the PHPStan
loader do not boot `App\Bootstrap`, read `config/local.neon`, send actual mail or
call AI. No environment migration or deployment was performed.

## Repeatable checks

Use PHP 8.2+ (inside the project Docker container when available):

```sh
php tests/Cases/Unit/Domain/WorkflowBaselineTest.phpt
php tests/Cases/Unit/Domain/DomainTransitionsTest.phpt
php tests/Cases/Unit/Domain/SubscriptionTest.phpt
php tests/Cases/Unit/Domain/Provider/ActivationTokenTest.phpt
php tests/Cases/Unit/Domain/Provider/ProviderRegistrationDataTest.phpt
php tests/Cases/Unit/UI/ProviderRegistrationFormTest.phpt
php tests/Cases/Integration/DDD/ServiceWiringTest.phpt
php tests/Cases/Integration/Customer/CustomerDemandTest.phpt
php vendor/bin/phpstan analyse -c phpstan-ddd.neon --memory-limit=512M
```

Windows PHPStan cannot reliably use a WSL UNC working directory; this run used a
temporary native-path copy of code/dependencies, without environment configuration.

## Remaining deployment verification

SQLite and the simulated stale pre-insert lookup verify state/rollback/conflict
handling, but do not prove MySQL locking under competing processes. Before rollout,
use a separate disposable MySQL database to exercise:

- simultaneous activation and resend, ensuring no active account receives a token;
- two registrations of one normalized email, yielding one provider and its coverage;
- two demand workers, preserving a single live lease and at most three invitations;
- closing/requeueing while a worker is running, rejecting its stale result;
- two delivery workers, preventing concurrent claims of one pending invitation.

After rollback that discards the UnitOfWork, reload/discard old entity references.
After a database write error closes the manager, finish the request instead of
continuing ORM work. The email callbacks are not a durable outbox; the existing
resend and access-recovery paths still cover process failure after commit.
