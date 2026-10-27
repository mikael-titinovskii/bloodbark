# Accounts and roles

Registration is the only way an account is born: it mails a generated password plus the link that confirms the address.
The reset flow is the only exception to "we choose the password" — a one-time link lets the account holder type a new one.

- `App\Entity\User` (`app_user`) holds the contact details, the roles, the consent record and the confirmation stamp
  (`confirmedAt`) with the hash of the live confirmation link. `ContactType` and `ContactWindow` are enums under
  `src/Entity` implementing `TranslatableInterface`, so the form choices and the account page translate their own labels
  — `contact.type.*` / `contact.window.*` in both catalogs.
- `POST /register` binds `App\Form\RegistrationType` onto a fresh `User`, then `App\Service\RegistrationService` generates
  the password (`App\Security\PasswordGenerator`: 20 characters, no punctuation, no lookalikes), hashes it, records
  consent with `app.legal.policy_version` and mails the plaintext and the confirmation link once — the row is flushed
  only after the mail is away, so a failing transport cannot leave an account whose password nobody received. Plaintext
  password and plain token exist only in that message: never log or store them.
- The sender address is `app.mailer.sender`, `no-reply@bloodbark.md` until the real domain is set (`MAILER_SENDER`
  overrides it). Bump `app.legal.policy_version` whenever the policy text changes: registrations store the version they
  accepted, and an unchanged number makes that record meaningless.
- Login is the firewall's `form_login` against `app_login` (`email`/`password` fields, `authenticate` CSRF token); logout
  is the framework-generated `_logout_main` route, posted with the `logout` token. `App\Security\UserProvider` replaces
  the entity provider, because a login form hands back whatever casing the visitor typed while addresses are stored
  lowercased.
- Every account is a `ROLE_USER`. `ROLE_MODERATOR` and `ROLE_SUPER_ADMIN` live in the same JSON column and are granted
  from the console: `php bin/console app:user:promote <email> moderator|super_admin`. The hierarchy in `security.yaml`
  makes them cumulative: super admin ⊃ moderator ⊃ user. The backoffice lists roles but does not edit them — `User`
  exposes `addRole()` and no setter on purpose, so a form cannot replace the whole column in one POST.
- The one exception is the address in `SUPERADMIN_EMAIL` (an env var, unset by default: set it in `.env.local` or the
  deployment environment). When that address registers, it stays a plain user until it follows the confirmation link —
  then `App\Service\EmailConfirmationService` grants `ROLE_SUPER_ADMIN`. The role hangs off confirmation, not
  registration, because registering only proves somebody typed the address; the backoffice address must not be takable
  by whoever types it first.
- Confirmation itself is `GET /confirm-email/{token}`: the token is stored hashed, spent on first use, and replaced by
  "send it again" on the account page (`POST /account/confirmation`). A confirmed address is not required to sign in, so
  an expired link is an inconvenience rather than a lockout.
- `access_control` matches local paths by regex (`^/(ru/)?account`), since every controller route exists once per
  locale. New protected areas follow the same shape — the backoffice matches `^/(ro|ru)/admin`, because its locale
  segment is mandatory rather than optional; see `src/Controller/Admin/AGENTS.md`.
- `PasswordResetRequest` (`app_password_reset_request`) keeps only the SHA-256 of a link token, one live row per account,
  valid for `PasswordResetService::TTL_HOURS`. `POST /forgot-password` answers the same sentence for a known and an
  unknown address (the form must not enumerate accounts), and `GET /reset-password/{token}` only renders the form — the
  token is spent by the POST that saves the new password, because mail clients and scanners open links on their own. The
  changed hash also logs that account's other sessions out, since the firewall compares it with the one stored at login.
- Mail is the expensive part of these flows, so `POST /register`, `POST /forgot-password` and the confirmation resend
  each spend one hit per submission through `App\Security\RequestThrottle`, keyed by client IP: five an hour each,
  declared in `framework.rate_limiter`. A refusal is a 429 carrying `Retry-After`, rendered by
  `templates/bundles/TwigBundle/Exception/error429.html.twig`. Login has its own ceiling in the firewall
  (`login_throttling`: 5 attempts per 15 minutes per account+IP, reset by a successful sign-in). Counters live in the
  `cache.rate_limiter` pool, which `cache:clear` does *not* empty — unstick a spent dev browser with
  `php bin/console cache:pool:clear cache.rate_limiter` (`ee-up` does it for the e2e stack).

## Analytics and cookie consent

- Analytics is opt-in and native. `vanilla-cookieconsent` owns the `bb_consent` browser cookie and left-side UI;
  `AnalyticsConsentService` also requires a matching server-side `analytics_consent` row at the current
  `app.analytics.consent_revision` before `AnalyticsRecorder` writes anything. Bump that parameter when the analytics
  purposes or collected data change. Withdrawing consent deletes the browser's previous events and blocks later writes
  while retaining the audit row; the separate EasyAdmin `forget` action also removes that row on manual request.
  Consent changes and event inserts share a PostgreSQL advisory transaction lock, so withdrawal cannot race an event
  into existence and simultaneous first choices cannot violate the consent UUID unique index.
- `app.analytics.retention_days` (182) controls the browser cookie, backend consent expiry and data retention. Run
  `php bin/console app:analytics:purge` daily in production: it removes older events and expired consent rows without
  events, taking the same per-consent locks before cleanup. `AnalyticsRequestGuard` throttles both public endpoints
  before payload mapping and rejects bodies over 8 KB, so malformed JSON cannot bypass the limiter.
- The consent row and its events use the library's random UUID, never the account, email or IP. Event properties are
  scalar-only, size-limited and restricted to `locale`, `route` and `value`; never put contact details, free text,
  medical data or exact location in them. `page_view` sends the canonical route name and locale, never the raw path,
  query string or route parameters (which can contain secret tokens). Successful registration records
  `registration_completed` through the same consent gate.
- Localized `POST /analytics/consent` and `POST /analytics/events` use `#[MapRequestPayload]`. The former is necessary
  to save the choice; the latter returns 204 without storing when consent is absent. Their public-ingestion rate limits
  live beside the mail limits in `framework.rate_limiter` and `RequestThrottle`.
