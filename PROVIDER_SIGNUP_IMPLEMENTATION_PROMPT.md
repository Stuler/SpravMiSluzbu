# Implement Free Provider Signup, Activation, and Sign-In

Work in the `SpravMiSluzbu` repository and follow its `AGENTS.md` instructions. Inspect the existing implementation before editing and preserve unrelated working-tree changes.

## Goal

Implement a complete provider account flow:

1. Free three-step provider registration.
2. Email activation using a secure one-time token.
3. Automatic sign-in after activation confirmation.
4. Provider-specific sign-in and logout.
5. Secured provider onboarding dashboard.

Provider social login, payments, password reset, profile editing, and admin approval are out of scope.

## Existing Worktree

Preserve these intended changes:

- `.github/workflows/deploy.yml` enables automatic Nettrine migrations on Roští `beta`.
- `.docs/rosti-test-deploy.md` documents migration conventions.
- Do not modify or revert the unrelated `var/log/.gitignore` change.

## Provider Registration

- Remove Stripe and the payment step from the React wizard.
- Keep three steps:
  1. Services and regions.
  2. Personal/contact credentials.
  3. Company and address.
- Require:
  - service category
  - region
  - first and last name
  - normalized email
  - password and confirmation, minimum 8 characters
  - phone
  - company name
  - street and street number
  - city
  - postcode
- Keep IČO optional.
- Treat server-side validation as authoritative.
- Prevent duplicate submissions and show Slovak validation and error messages.
- Register providers in `fresh` state without subscription or payment records.
- Enforce unique normalized email within the `provider` table. User and provider accounts may share the same email.

Use a CSRF-protected Nette Form wrapper for the React widget instead of an unprotected JSON registration endpoint. Keep presenters thin and delegate registration to the provider domain workflow.

Registration must transactionally create:

- provider
- provider-region relations
- provider-service-category relations
- activation token state

Mail failure must not roll back the account. Log the error and return a result that allows the UI to offer activation-email resend.

## Remove Stripe Signup Behavior

Remove:

- React payment step and unused Stripe components
- payment intent and Stripe public-key API actions
- Stripe calls from registration
- `pending_payment` registration behavior
- unused Stripe frontend and PHP dependencies and DI configuration
- payment-specific signup copy and CSS

Retain subscription domain entities and tables for future billing.

## Activation Tokens

Replace the existing predictable `md5(microtime())` provider hash.

Generate 32 random bytes using `random_bytes()`. Send the raw base64url token in the email, but store only its SHA-256 hash.

Token rules:

- expires after 24 hours
- single use
- resend invalidates the previous token
- token is cleared after activation
- raw token is never persisted or logged

The activation email must be in Slovak with:

- subject: `Aktivujte si účet poskytovateľa Bripeon`
- activation button
- plain URL fallback
- expiration information

## Activation Flow

The email link must open a branded confirmation page without mutating account state.

- Send `Referrer-Policy: no-referrer`.
- Render a CSRF-protected button labeled `Aktivovať a prihlásiť sa`.
- On POST, validate the token again inside a transaction.
- Change provider state to `activated`.
- Set the activation timestamp.
- Clear activation token fields.
- Regenerate the session ID.
- Sign the provider in using a provider identity.
- Redirect to `Front:ProviderDashboard:default`.

Invalid, expired, and consumed tokens must show a generic Slovak error and link to resend activation.

Add a resend-activation Nette Form:

- always return a generic response to prevent account enumeration
- issue a new token only for a fresh provider
- apply a five-minute per-account cooldown
- do not reveal whether the submitted email exists

Existing activated providers remain activated. Existing fresh providers with legacy hashes must request a new activation email.

## Provider Authentication

Do not change the existing user and admin authenticator behavior.

Add a provider-specific authentication service used by `ProviderSignPresenter`:

- query only the `provider` table
- normalize email
- verify the password before revealing activation state
- reject wrong credentials generically
- reject blocked providers
- tell a correctly authenticated fresh provider that activation is required
- update `dateLastLogin`
- return a Nette identity containing:
  - provider ID
  - `provider` role
  - `accountType=provider`
  - scalar provider state ID
  - email and display data

Provider sign-in must use Nette Forms with CSRF protection.

Session behavior:

- normal session: 20 minutes
- `Zapamätať si ma`: 14 days
- redirect successful sign-in to the provider dashboard
- implement provider logout as a CSRF-protected POST

## Provider Dashboard

Add a secured Front provider area, separate from Admin.

Access requires:

- authenticated session
- `provider` role
- `accountType=provider`

The initial dashboard is read-only and shows:

- activation and account status
- company information
- contact information
- address
- selected service categories
- selected regions

Do not add profile editing or dead placeholder links.

Update public navigation so anonymous visitors can access provider registration and provider sign-in, while authenticated providers see dashboard and logout actions.

## Database Migration

Use the existing Nettrine and Doctrine migration package and conventions.

Create a new migration in `db/Migrations` under `Database\Migrations` that:

- normalizes existing provider emails
- aborts with a clear message if normalized duplicates exist
- adds a unique provider email index
- replaces legacy `hash` with nullable `activation_token_hash CHAR(64)`
- adds nullable `activation_token_expires_at`
- adds nullable `activation_sent_at`
- clears legacy token values
- preserves activation status and `date_activated`
- includes a useful `getDescription()`
- provides a safe `down()` implementation where possible

Do not edit previously executed migrations.

The existing Roští beta workflow must continue running:

```bash
php bin/console migrations:migrate --no-interaction --allow-no-migration
php bin/console migrations:status --no-interaction
```

Production migrations remain manual.

## API and UI Changes

Keep these read endpoints:

- categories
- regions
- cities

Remove public endpoints for:

- provider creation
- payment intent creation
- Stripe public key

Use Slovak for all provider-facing labels and messages. Use English for code, identifiers, comments, exceptions, logs, and documentation.

## Tests

Add focused tests covering:

- successful provider registration
- server-side validation
- optional IČO
- duplicate normalized email
- invalid category, region, or city
- CSRF rejection
- registration transaction behavior
- email delivery failure
- activation GET does not mutate
- valid activation POST
- expired, invalid, and reused token
- resend token rotation and cooldown
- sign-in with correct and incorrect credentials
- fresh and blocked provider sign-in
- provider identity creation
- dashboard authorization
- logout
- coexistence of user and provider accounts using the same email

## Verification

Run the smallest relevant checks:

```bash
docker compose config
docker compose ps
docker compose exec php php -l path/to/each/touched.php
docker compose exec php php bin/console migrations:status
docker compose exec php vendor/bin/phpstan analyse -c phpstan.neon --memory-limit=512M
npm run build
curl -I http://localhost:8082
```

Validate the workflow YAML:

```bash
python3 - <<'PY'
import yaml
from pathlib import Path

with Path(".github/workflows/deploy.yml").open() as f:
    yaml.safe_load(f)

print("yaml ok")
PY
```

Smoke-test the complete flow in a browser:

1. Register a provider.
2. Inspect the generated development activation email.
3. Open the activation confirmation.
4. Activate and verify automatic sign-in.
5. Verify dashboard contents.
6. Log out.
7. Sign in again using provider credentials.

Do not stop at analysis. Implement, migrate, build, test, and report changed files, verification results, and any remaining risks.

