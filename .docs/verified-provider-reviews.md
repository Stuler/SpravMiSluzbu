# Verified provider reviews

## Implemented workflow

The verified owner of a demand selects an active provider who has accepted its invitation and still accepts new demands. `JobFacade` locks demand, invitation and provider; a nullable unique `job.active_demand_id` permits one current job while cancelled rows retain history. Selection permanently stops matching for that demand. Replacement uses an already accepted invitation; automatic matching is not resumed. A cancelled provider cannot be selected again by replaying an old form.

Provider agreement confirmation is separate from customer work reporting and collaboration verification. Work may be completed, partially completed, ongoing, or not started. Only completed/partial work permits a review. Closing the demand does not report completed work and does not grant review permission. Work reports require a valid nonfuture date and description and cannot be rewritten after reporting.

`ReviewFacade` uses the existing active-customer or scoped guest-token authorization through `JobFacade`. A unique `review.job_id` permits one review, including pending/rejected reviews. Repeated submission returns the existing review without changing rating/text or sending another notification. Confirmed or administratively verified work publishes immediately; unverified/disputed work waits for administration. The provider can reply once to a published review. Quality disagreement has no publication effect. Explicit denial of collaboration moves a published review to pending until an administrator resolves the relationship.

Admin decisions require the existing active admin permission check, a reason, and an audit record. Review decisions also retain the deciding administrator and time on the review. Job disputes without a review can be resolved from the queue and are recorded in the existing audit. Documents may be checked through support; no upload workflow is added.

Public profiles display only published reviews and aggregate all published ratings. Customer identities, contact details and work descriptions are not exposed on the public profile. User text uses Latte escaping. Guest access recovery continues to rotate the existing 30-day demand token and invalidate old session grants. Emails are sent after the transaction commits, with delivery failures logged without tokens or message contents. SMTP failures do not undo saved jobs/reviews; this MVP does not promise automatic email retries.

## Reviewable rollout gates

Migration: `db/Migrations/Version20261008140000.php`. It was manually applied to the authorized local Docker database on 2026-10-09 after a backup. Automatic migration of the isolated beta database during deployment was explicitly approved on 2026-10-09; see `rosti-test-deploy.md`. Production still requires an approved schema backup and migration procedure. Deploying this code before its schema exists will fail on demand reads; coordinate code and schema deployment in a maintenance window. Automatic production migrations are not authorized.

1. Jobs pilot: verify accepted invitation selection, repeat selection, provider confirmation/refusal, replacement from another accepted invitation, reporting all four outcomes, nonfuture dates, and no further provider outreach. Check existing guest links and recovery for expired links.
2. Reviews pilot: verify unauthorized users/providers, CSRF rejection, completed/partial eligibility, closure without eligibility, unconfirmed work pending admin review, negative confirmed review publication, collaboration denial and both admin decisions with recorded actor/reason.
3. Public display: verify aggregate excludes pending/rejected reviews, escaped text, category and work month, partial outcome label, verification explanation, one provider reply, and the three postcommit emails for both positive and negative experiences.

These gates describe controlled pilot verification of the integrated change; the code does not add feature flags or three independent migrations. Keep the pilot on a separate beta domain/database/secrets. Before public release, also run two competing MySQL/MariaDB connections selecting providers for the same demand and submitting reviews for the same job. Verify one current job/one review and normal response handling under contention. The SQLite suite verifies uniqueness and repeated submissions but cannot establish MySQL lock behavior or SMTP delivery.

## Local verification

- PHP syntax checked with locally available PHP 8.3.7 because Docker Desktop was unavailable.
- `tests/Cases/Integration/Customer/CustomerDemandTest.phpt`: transient SQLite database, captured mail transport, actual Nette presenters/Latte/forms, workflow authorization, database uniqueness, replay, guest recovery, administrative decisions, XSS escaping and CSRF rejection.
- `tests/Cases/Integration/DDD/ServiceWiringTest.phpt`: actual service registrations compile and autowire in isolation, including the new repositories and facades.
- `docker compose config --quiet` validates configuration. Container checks and HTTP smoke tests require a running local Docker Desktop; localhost:8082 was unavailable during implementation.
- Static analysis was unavailable: Windows PHPStan cannot resolve this UNC checkout, and WSL has PHP 8.1 while dependencies require PHP 8.2+. Run the scoped PHPStan configuration in Docker before beta deployment.
- Existing local warning: `Cannot load Xdebug - it was already loaded`; judge checks by their result and exit code.

The original checks above were followed by local Docker verification on 2026-10-09: the complete integration suite including review/UI scenarios passes on PHP 8.2.31 with both SQLite and disposable MySQL 8.0.46 fixtures; actual service wiring and scoped PHPStan also pass. AI remains fake and mail captured. See `admin-mvp.md` for the local migration record and HTTP smoke checks. These results do not replace the competing Job/Review writer checks or real-mail pilot gates above. No beta/production migration, deployment or real SMTP delivery was performed.
