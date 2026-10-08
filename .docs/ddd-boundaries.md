# DDD boundaries and transaction ownership

## Scope

Keep the modular monolith, Nette UI, Doctrine mapping and current database values.
These decisions describe the existing marketplace behavior; they do not enable
production billing, automatic migrations or a new customer registration workflow.

## Modules and aggregates

| Module | Responsibility and consistency boundary |
| --- | --- |
| Provider | Provider account, activation, profile and service/region coverage. Coverage changes are saved with the profile and audit in one transaction. |
| Customer | Customer account, account tokens and session revocation. |
| Demand | Demand details, ownership, verification and closure. A closed demand is terminal. |
| Matching | Selection and invitations. All writers lock the demand before allocating invitations. The lifetime limit is three distinct providers, including declined or cancelled invitations. |
| Catalogue | Service hierarchy and active locations. Hierarchy writes remain serialized to prevent concurrent cycles. |
| Subscription | Subscription and plan. Currently an unused model scaffold, not an active payment workflow. |
| Admin | Authorized application operations and atomic audit records; does not define alternative marketplace rules. |

Doctrine relationships do not automatically make connected objects one aggregate.
In particular, invitations do not own provider profiles or customer accounts.
Matching reads eligibility again before saving its selection.

## State transitions and repeated requests

- Demand: `pending_verification -> ready`; an authenticated active customer starts
  with a verified demand. Processing an open verified demand can result in `ready`
  (retry), `needs_review`, `no_providers` or `providers_invited`.
- Requeue: any open verified demand becomes `ready`; existing invitations remain
  and still count towards the lifetime limit. Requeue resets attempts and leases.
- Close: any open demand becomes `closed`. Repeated verification or closure is a
  no-op, and closure cannot be undone by a stale worker.
- Worker claim: only a ready verified demand whose backoff and lease have expired.
  AI runs outside the database transaction. Saving checks the current lease under
  the demand lock; an expired/replaced lease has no authority.
- Provider: a fresh non-deleted, unblocked account with a valid activation token
  becomes active. Activation stamps the date and consumes the token atomically.
  Reuse and expired tokens fail. Resend rotates only a fresh account's token, with
  a five-minute cooldown checked under the same provider lock as activation.
- Administrative blocking preserves the activation state and revokes sessions.
  Repeating the same block/unblock request must not revoke sessions again.
- Invitation response: `pending -> accepted | declined`; the first response is
  final. Repeating a response preserves it. A closed demand cannot receive a new
  response through the application service.
- Delivery: `pending -> sending -> sent | unknown | cancelled`. An ambiguous SMTP
  result stays `unknown`; it must not be retried automatically. Manual retry of
  `sending`/`unknown` is allowed after five minutes, while the demand remains open.

## Layers

- UI owns HTTP, sessions, forms and translated presentation.
- Application workflows currently remain in the existing domain facades. They
  coordinate authorization, repositories, domain operations and transactions.
- Entities/value objects protect state and profile invariants independently of UI.
- Persistence repositories implement queries and locking, never commit workflows.
- Infrastructure implements external AI/mail integrations. Further facade splitting
  and presentation extraction belong to subsequent PRs, not this first series.

## Transaction contract

The outer application operation owns begin/final flush/commit/rollback. An early
flush is allowed to allocate an ID for an audit, without committing. Nested calls
participate in that operation and never commit independently. An inner failure
makes the operation rollback-only, even if its caller catches the exception.
Audit and the marketplace mutation commit together. On rollback the UnitOfWork is
cleared if objects were mutated or flushed; callers discard/reload those entity
references. A validation rejection before mutation can retain loaded objects for
form rendering. Database write failures close
the manager, and that request must not continue using it.

Mail callbacks registered through `afterCommit()` are deferred until the outer
application operation commits and discarded on rollback. Callback errors cannot
roll back committed data. These callbacks are not a durable outbox: process death
after commit still requires the existing recovery/resend workflows. Registration
results confirm email delivery only when it has actually occurred.
The existing invitation queue retains its ambiguous-delivery behavior. Provider
registration must translate an email uniqueness conflict without reporting other integrity
errors as duplicate emails. A pre-insert lookup is not concurrency protection.

## Verification and rollout

Use entity tests for transitions, profile rules and exact expiration boundaries.
Use isolated SQLite integration tests for mappings, persistence round trips,
rollback/audit atomicity and existing customer/matching/admin behavior. SQLite
cannot prove MySQL row-lock behavior: run a separate, explicitly isolated MySQL
concurrency suite for activation/resend and two workers before deployment.

Do not use Bootstrap/local.neon for isolated tests. Never load production database
configuration. Do not automatically apply any schema change. Subscription tables
are absent from the checked-in bootstrap dump/migrations; schema provisioning is
a separate operation before billing is enabled.
