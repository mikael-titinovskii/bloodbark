# Bloodbark

An information and matching platform that connects dog owners. Owners publish declared facts about
their dog (including blood type) and a district, and other owners filter on those facts and ask to
be put in touch.

Bloodbark is **not** a veterinary service, **not** an emergency service, and **not** a broker of
biological material. The system displays and filters what owners declare; it never determines
compatibility, ranks donors, or advises on a transfusion. Every compatibility decision belongs to a
licensed veterinarian. The binding scope — settled decisions, the closed listing schema, required
copy — is in [`legal.md`](legal.md), which must be read before adding a page, a field or a label.

## Status

Early. The site currently ships the account layer:

- Homepage, in Romanian (unprefixed) and Russian (`/ru`)
- Registration with a generated password and an address-confirmation link
- Sign-in with CSRF protection and login throttling
- Password reset by e-mailed, single-use, 2-hour link
- Account page with the stored consent record and a "resend confirmation" action
- Localised 404 / 429 / generic error pages
- Console command to grant a moderator or super-admin role

The listing, search and contact-relay features described by the schema in `legal.md` are not built
yet. `src/DataFixtures/AppFixtures.php` is still empty for the same reason.

## Stack

| Layer     | Choice                                                              |
| --------- | ------------------------------------------------------------------- |
| Backend   | PHP 8.5, Symfony 8.1 (attribute routing, autowiring)                 |
| Database  | PostgreSQL 18, Doctrine ORM 3 with migrations                        |
| Templates | Twig 3, Tailwind CSS 4                                               |
| Front-end | Plain Vite 8, vanilla ES modules, ESLint 10                          |
| Tests     | PHPUnit 13, Codeception 5 (Smoke + Acceptance suites)                |
| Quality   | php-cs-fixer (`@Symfony`), PHPStan level 8 with strict rules         |
| Runtime   | Docker Compose: nginx, PHP-FPM, Postgres, Mailpit, and a Node toolchain |

Nothing runs on the host: PHP, npm and the database all live in containers.

## Requirements

- Docker with Compose v2
- `make`
- The repository checked out locally (the whole workflow is Makefile targets)

## Getting started

```sh
git clone https://github.com/mikael-titinovskii/bloodbark.git
cd bloodbark

# 1. Dev TLS certificate (git-ignored, so a fresh clone has none).
mkdir -p docker/nginx/certs
openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
  -keyout docker/nginx/certs/selfsigned.key \
  -out docker/nginx/certs/selfsigned.crt \
  -subj "/CN=localhost"

# 2. Start the stack and build the front-end bundle.
make up
make assets

# 3. Create and migrate the dev and test databases.
make db-create
make migrate
```

Then open:

- https://127.0.0.1:8000 — the site (self-signed certificate: accept the browser warning)
- http://localhost:8025 — Mailpit, which catches every message the dev stack sends
- `localhost:5432` — PostgreSQL, published by `compose.override.yaml`

Registration mails the generated password, so creating an account means reading it in Mailpit.

### Optional local settings

`.env` is committed and holds defaults only. Put machine-specific values in `.env.local`:

```dotenv
# The address that becomes ROLE_SUPER_ADMIN once it confirms itself.
SUPERADMIN_EMAIL=you@example.com

# Sender of transactional mail; defaults to no-reply@bloodbark.md.
MAILER_SENDER=no-reply@example.com
```

Alternatively, promote an already-registered account from the console:

```sh
make shell CMD="php bin/console app:user:promote you@example.com super_admin"
```

## Everyday commands

Everything goes through `make`; the targets shell into the containers.

| Command                             | What it does                                                     |
| ----------------------------------- | ---------------------------------------------------------------- |
| `make up` / `make down`             | Start / stop the dev stack (the database volume survives)         |
| `make db-create`                    | Create the dev and test databases if they are missing             |
| `make migrate`                      | Apply pending Doctrine migrations                                 |
| `make shell`                        | Shell in the `php` container (Alpine — `sh`, no bash)             |
| `make shell CMD="php bin/console …"`| Run one command in the `php` container (`php` prefix is mandatory) |
| `make assets`                       | Build the Vite bundle into `public/assets/`                       |
| `make assets-watch`                 | Rebuild on change (templates included) — leave it in a terminal   |
| `make npm CMD="install foo"`        | Any npm command, in the `node` service                            |
| `make lint`                         | php-cs-fixer, PHPStan, ESLint, container/YAML/Twig linters        |
| `make cs-fix`                       | Apply the php-cs-fixer fixes `make lint` only reports             |
| `make unit`                         | PHPUnit against the test database                                 |
| `make ee` / `make ee-up` / `make ee-down` | End-to-end: rebuild the e2e database, seed fixtures, run Codeception |

`bin/*` is not executable on the bind mount, so console and test binaries are always invoked as
`php bin/console …` and `php bin/phpunit`.

## Project layout

```
assets/          Vite sources; entries declared by hand in vite.config.mjs
config/          Symfony configuration (routes.yaml registers every route per locale)
docker/          nginx site config, PHP image, dev certificate (git-ignored)
migrations/      Doctrine migrations — the only way schema changes ship
public/          Front controller; public/assets/ is Vite build output
src/Command      Console commands
src/Controller   Thin HTTP actions, one per flow
src/Entity       Doctrine entities mapped with attributes
src/Form         Form types
src/Mailer       Templated transactional mail
src/Security     User provider, password generator, request throttling
src/Service      Business rules, transactions, persistence
templates/       Twig pages, e-mails and error pages
tests/           PHPUnit, plus the Codeception suites (Acceptance, Smoke)
translations/    messages.<locale>.yaml, validators.<locale>.yaml
```

## Routes

Romanian is the default locale and is served without a prefix; Russian is under `/ru`. The router
registers each controller route once per locale (`app_home.ro`, `app_home.ru`), so links are
generated from the unsuffixed name and follow the current locale.

| Path                                       | Route                      | Notes                                             |
| ------------------------------------------ | -------------------------- | ------------------------------------------------- |
| `/`                                        | `app_home`                 | Homepage                                          |
| `/register`, `/register/sent`              | `app_register*`            | Sign-up and its confirmation page                 |
| `/login`                                   | `app_login`                | Rendered by the controller, posted to the firewall |
| `/account`                                 | `app_account`              | `ROLE_USER` only                                  |
| `/account/confirmation`                    | `app_confirm_email_resend` | POST, CSRF-protected                              |
| `/confirm-email/{token}`                   | `app_confirm_email`        | 64-hex single-use token                           |
| `/forgot-password`, `/forgot-password/sent`| `app_forgot_password*`     | Same answer whether or not the address exists     |
| `/reset-password/{token}`                  | `app_reset_password`       | Token only spent by the POST                      |

## Accounts and roles

Every account is a `ROLE_USER`. `ROLE_MODERATOR` and `ROLE_SUPER_ADMIN` are granted from the console
(`app:user:promote`) or automatically to `SUPERADMIN_EMAIL` when that address confirms itself.
Registration always creates an unconfirmed account with a generated password; nothing lets a user
choose one, and a confirmed address is what makes the super-admin promotion proof of ownership.

Registration, password reset and confirmation resend are rate-limited per client IP (5 per hour,
sliding window) and answer 429 with a `Retry-After` header, rendered as a normal error page.

## Configuration

| Variable           | Purpose                                                           |
| ------------------ | ----------------------------------------------------------------- |
| `APP_ENV`          | `dev` by default; `test` for the e2e stack                        |
| `APP_SECRET`       | Symfony secret; the dev value is committed, real ones are not      |
| `DATABASE_URL`     | `postgresql://bloodbark:bloodbark@database:5432/bloodbark` in dev   |
| `MAILER_DSN`       | `null://null` in `.env`; `.env.dev` points at Mailpit               |
| `SUPERADMIN_EMAIL` | Address promoted to super admin on confirmation                    |
| `MAILER_SENDER`    | Overrides the default transactional sender                         |
| `E2E_BASE_URL`     | Where the Codeception suites point (`https://nginx` inside compose) |

Real secrets belong in `.env.local` (git-ignored) or in the Symfony secrets vault.

## Testing

```sh
make unit    # PHPUnit: controllers via WebTestCase, services via KernelTestCase
make ee      # Codeception end-to-end: rebuilds the e2e database, loads fixtures, runs both suites
```

`make ee` is the outermost gate. It brings up a second Compose project (`bloodbark-e2e`, same files,
`APP_ENV=test`, its own network and Postgres volume), rebuilds and migrates that database, loads
`src/DataFixtures`, then runs:

- **Smoke** — plain HTTP through nginx, PHP-FPM and the database (`tests/Smoke`)
- **Acceptance** — headless Chromium in the `php` container (`tests/Acceptance`)

Failure screenshots and response HTML land in `var/codeception/`. Add seed data to
`src/DataFixtures/AppFixtures.php`, not to the suites, so every run starts from the same state.

`make unit` uses `bloodbark_test` inside the dev stack; run `make db-create` once and apply
migrations with `php bin/console doctrine:migrations:migrate --env=test`.

## Code style and static analysis

`make lint` runs php-cs-fixer in dry-run mode, `codecept build`, PHPStan at level 8 over `src/` and
`tests/`, ESLint, and the container, YAML and Twig linters. `make cs-fix` applies the style fixes.
A change is not done while any of them is red. PHPStan resolves services from
`var/cache/dev/App_KernelDevDebugContainer.xml`, so warm the dev cache first if that file is missing.

Front-end sources are linted with `npm run lint` (part of `make lint`); fix with
`make npm CMD="run lint -- --fix"`.

## CI

`.github/workflows/ci.yml` runs on pull requests to `main` and executes `make lint` and `make unit`
on the runner (with `PHP=` / `NODE=` so the targets use the runner's own binaries instead of
Compose). End-to-end suites run locally via `make ee`, not in CI.

## Conventions

- Symfony framework metadata lives in PHP attributes — no YAML/XML routing or mapping.
- Controllers bind request data and delegate; business rules, queries and persistence live in
  services.
- Every user-facing string goes through `|trans`; the brand name is the only untranslated text.
  Catalogs never fall back to English.
- Schema changes go through migrations; never `doctrine:schema:update`.
- No git hooks. Formatting and tests are commands someone runs on purpose.

[`AGENTS.md`](AGENTS.md) is the detailed reference for the stack, the local workflow and the
testing setup; [`legal.md`](legal.md) is the reference for what the product may and may not do.
