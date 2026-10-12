# Customer demands and registered-provider matching MVP

## Scope and architecture

The existing Nette/Doctrine modular monolith now supports customer registration,
activation, login, password recovery, guest/customer demands, asynchronous AI
classification and matching, provider invitations/responses, and admin operations.
There is no internet discovery, MCP server, separate backend, or billing change.
Only registered, activated, non-deleted providers accepting new demands qualify.

Coverage uses all enabled municipalities in the existing Slovak city catalogue and
providers' declared regions. It does not calculate driving distance or a radius.
Services use existing non-deleted category_service rows. Exact category matching
is intentional: registration under a broad parent does not imply every specialty.
The current candidate pool is capped at 50 eligible providers, ordered by ID;
rotation/fairness and finer geographical coverage are follow-up improvements.

## Workflow and routes

- /user-sign/up, /user-sign/in, /user-sign/forgot: customer accounts.
- /customer-dashboard/default: current customer's paginated demands.
- /demand/create: shared guest/customer form. `?categoryId=<id>` preselects an active service
  (used by the home page catalogue); unknown, retired or malformed values are ignored. The form
  offers services grouped under their main category; a main category with active services is
  not selectable itself, one without services stays selectable (`CategoryCatalogue`).
- /demand/recover: replace a guest link using reference and email.
- /demand/detail?reference=...: private detail, progress and provider responses.
- /provider-demand/default: provider inbox, availability and service description.
- /provider-demand/detail?id=...: invitation restricted to its provider.
- /admin/demand: admin list; detail supports requeue, AI preview, manual selection,
  delivery recovery and AI history. Access requires the existing admin ACL.

Demand states: pending_verification -> ready -> invited, needs_review, or
no_providers. Customers can close demands; closed is terminal. A guest verifies
email before matching. Active customer accounts submit verified demands directly.
Requests spanning multiple categories require admin review in this MVP.

Matching uses a persistent database queue, five-minute processing leases and a
lease token checked before committing results. Transient AI errors retry after ten
minutes, with at most three AI processing attempts. Missing configuration goes to
admin review. Admin requeue resets the attempt budget without duplicating invitations.

The database enforces one invitation per demand/provider. A demand has a lifetime
limit of three invitations, including declined or cancelled invitations. Matching
creates invitations; a separate worker option delivers emails. The provider sees
a service summary and municipality before accepting. Contact fields and the original
request become available after acceptance. The first accept/decline response is final.
Customers see provider contact information after that provider accepts.

The mail outbox marks an invitation as sending before SMTP. If delivery fails or a
worker stops mid-send, automatic retries are suppressed because the message might
already have been delivered. Admin can explicitly retry after five minutes and an
acknowledgement of possible duplicates. Closure, blocking, deletion and availability
are checked before delivery. SMTP and database writes cannot form one atomic transaction.

## Customer security

Demand.customer is optional. Guest demands do not create synthetic accounts or attach
to a later account merely because the email matches. Customer identities use a separate
account discriminator and the member role; provider IDs cannot unlock customer pages.
A customer demand always uses the account email, regardless of submitted contact email.

Activation tokens last 24 hours and password-reset tokens one hour. Both are single-use
SHA-256 hashes in customer_token. Replacement invalidates earlier tokens of the same
purpose. Password reset increments customer_session_version; protected customer routes
check it against the database. Deleted and blocked accounts cannot use the customer flow.
Legacy predictable activation links are rejected; fresh customers can request a new link.

Guest management links last 30 days. Their session grants last two hours and are checked
against the current hash and expiry. Link recovery rotates the token. Email-link GETs
exchange a bearer token for a session grant and redirect to a clean URL. Activation,
verification, password changes, closing and provider/admin actions require CSRF POSTs.
A reference alone grants no access. Sensitive pages use no-store and no-referrer headers.

Public forms use per-IP/per-email file-based rate limits under
%tempDir%/public-rate-limit. This assumes one host or a shared filesystem. Expired files
need periodic cleanup; use shared storage before scaling across independent hosts.
A session-bound nonce and a unique submission key prevent duplicate demand submission.
The request logger no longer dumps request bodies or query parameters. Configure reverse
proxy/web-server logs to redact token query parameters before release.

## AI configuration and prompt tuning

Set OPENAI_API_KEY and OPENAI_MODEL in the target runtime; Docker Compose forwards both
to PHP. No model or real credential is hard-coded. Select a model supporting Responses
Structured Outputs and evaluate it before enabling automatic matching. The client uses
https://api.openai.com/v1/responses with strict JSON schemas, store=false, a 60-second
request timeout and a 2,000-token output limit. It has no tools, web access or SQL access.

Prompt sources:

- resources/prompts/demand-analysis-v1.txt
- resources/prompts/provider-ranking-v1.txt

The first call extracts category IDs, a summary and review reasons from the existing
catalogue. The second ranks at most 50 eligible candidate IDs with service descriptions.
The backend validates every returned ID and rechecks eligibility before persisting
invitations. AI cannot add providers or bypass the three-invitation limit.

Each successful API response records input/output, resolved model, prompt/schema content
hash, tokens and elapsed time in ai_run. Preview calls are marked separately and create
no invitations. Failures produce a retry/review state; they do not currently create a
separate ai_run record. Admin history shows the latest 20 runs for a demand.

Explicit contact fields are excluded from AI payloads. Known customer names, emails and
phones are also redacted from text where recognized. This is best-effort redaction, not
guaranteed anonymization: free text can contain other personal data or addresses. Treat
AI audit records as private demand data and set a retention/deletion policy before launch.
store=false is an API request setting, not a claim of zero data retention by the provider.

Use the synthetic Slovak fixtures in tests/Fixtures/Matching/analysis-cases.json when
changing prompts or models. They cover clear tasks, typos, conflicting hints, multiple
services, unsupported requests and prompt injection. The command below is opt-in because
it performs paid API calls, but writes no demand data and sends no email:

```sh
php bin/console demands:evaluate --live
```

Without --live it makes no API calls. These initial fixtures measure classification and
review behavior, not ranking quality or production accuracy. Extend them using reviewed,
anonymized examples from the real catalogue and compare results before prompt releases.
Admin's AI preview is another paid API path but does not contact firms.

## Manual migration and startup

Neither migration has been applied to any application database by this implementation:

1. db/Migrations/Version20261007120000.php normalizes customer emails, rejects duplicates,
   adds a unique email index and session version, makes address fields optional, and
   creates customer_token and demand.
2. db/Migrations/Version20261007130000.php adds provider availability/service descriptions,
   demand queue fields, provider_invitation and ai_run.

Review and apply both manually in the intended isolated environment before releasing
these PHP changes. Resolve normalized-email duplicates explicitly. MySQL DDL is not
transactional: back up and inspect partial application before retrying. Rollback guards
protect populated demands/invitations; export AI history before any rollback as well.
Existing providers default to accepting demands, but activation and region/category
coverage are still required. No automated test/production migration was introduced.

3. db/Migrations/Version20261012120000.php seeds the public service catalogue (main categories
   Interiér, Exteriér, Záhrada, Ostatné and 52 services). It only inserts rows that have no
   active row with the same name under the same parent: existing rows (and their provider and
   demand links) are reused, and retired rows are never revived. It can be re-run safely and
   has no automatic rollback. Beta applies it with the other pending migrations during deploy;
   production still requires the manual migration procedure. The SQL was exercised in a SQLite
   simulation against the 2025-09-04 dump rows (empty and populated tables, repeated run), not
   on MySQL/MariaDB. A leftover row such as "Brány a dvere 2"
   stays active until it is retired in administration.
4. db/Migrations/Version20261012130000.php retires leftover test categories by name ("Test",
   "Test 1", "Test 2", "Testovanie 2") and their direct children. It only sets date_deleted
   (the same soft delete as retirement in administration), so rows, provider links and demand
   history are preserved. It is safe to re-run and has no automatic rollback. Verified by a
   SQLite simulation against the 2025-09-04 dump rows, not on MySQL/MariaDB.

Configure canonical HTTPS links (including contributte.console.url for CLI-generated
links), SMTP, OpenAI environment variables, and the existing catalogue/state/role seeds.
Verify deployment configuration and the queue against the target MySQL/MariaDB version.
First test mail with designated test accounts and the isolated environment's secrets.

Worker commands, after configuration and manual migrations:

```sh
# May call AI and create invitations; does not send provider email.
php bin/console demands:work --limit=10

# Also attempts delivery of up to 10 queued provider invitation emails.
php bin/console demands:work --limit=10 --send
```

The limit applies independently to processing and delivery. Schedule the latter under
the application user, for example once per minute, only after staging verification.
No cron or live worker has been started by this change. Customer activation, recovery
and receipt emails use the existing mail transport immediately after form submission.
Do not confuse the worker's email flag with disabling all application mail.

## Verification and remaining rollout checks

Run with PHP 8.2+ and pdo_sqlite:

```sh
php tests/Cases/Integration/Customer/CustomerDemandTest.phpt
```

The suite loads no config/local.neon, uses SQLite in memory, fake AI and captured mail,
renders the real forms/templates and exercises POST handlers. It covers customer token
purpose/expiry/reuse, session revocation, guest/customer ownership, duplicate submissions,
CSRF, category/provider validation, activation/availability/region filtering, invitation
caps, retry behavior, lease replacement, provider responses, contact visibility, admin
permissions and command email/API defaults. Existing legacy provider join mapping
warnings are excluded from metadata validation; new mappings are validated.

SQLite does not validate MySQL locking or migration behavior. Docker PHP 8.2, live HTTP,
real SMTP and OpenAI quality evaluation must still be checked in a configured runtime.
Full application container startup has not been verified; the isolated UI tests inject
the services directly. The integration suite, lint of 31 PHP files, service/fixture
parsing, Docker Compose configuration and scoped whitespace checks passed locally.
During implementation Docker Engine and localhost:8082 were unavailable. Checks used
local PHP 8.3.7, which reports an existing duplicate-Xdebug warning. The standard Docker
image lacks pdo_sqlite; use an isolated test runtime for this suite.

Monetization, SMS, attachments, live chat and automatic contact with external/unregistered
firms are outside this implementation. Reactions currently appear in the customer detail;
there is no additional customer email when a provider responds.
