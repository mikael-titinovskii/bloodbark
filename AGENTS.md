# Project

**Role:** information and matching platform connecting dog owners. Not a veterinary service. Not an emergency service.
Not a broker of biological material.

Legal anchors, settled decisions, hard prohibitions, the closed listing schema, copy rules, required strings and the
escalation list live in `legal.md`. Read it before adding a feature, a field, a label or a page.

Directory-specific guidance lives in nested `AGENTS.md` files under `src/`, `assets/`, `templates/`, `tests/`, and
`.github/`.

## Symfony

- Framework metadata goes in PHP attributes, never YAML/XML: `#[Route]`, `#[MapRequestPayload]`, `#[MapQueryString]`,
  `#[Assert\...]`, `#[AsCommand]`, `#[AsEventListener]`, `#[AsMessageHandler]`, `#[AsAlias]` / `#[AsTaggedItem]` /
  `#[Autoconfigure]`. Autowire and autoconfigure; use `#[Autowire]` / `#[Target]` in the class when a type-hint can't
  express it. A YAML service definition is the last resort.
- Controllers stay thin: bind request data, call a service, return a response. Business rules, query building,
  transactions and persistence belong in services.
- Use the components instead of hand-rolling: Form, Validator, Serializer, Messenger, Security (voters, authenticators),
  Twig `path()` / `url()`, `symfony/lock`'s `LockFactory` for mutual exclusion. Before writing locks, queues, caches,
  HTTP clients, mailers or schedulers — or adding a library — check whether a Symfony component covers it.
- Prefer `#[MapRequestPayload]` / `#[MapQueryString]` over `json_decode()` or `SerializerInterface` by hand; install the
  packages rather than falling back to manual parsing.
- Constructor property promotion, `readonly` for DTOs and value objects. Don't mark a service `readonly` if it might
  become `lazy: true` — a lazy proxy can't extend a `readonly` class.

## Languages

Romanian is the default locale, served without a prefix; Russian under `/ru`. `framework.enabled_locales` in
`config/packages/translation.yaml` is the single list — the switcher reads it through the `supported_locales` Twig
global.

- Locale lives in the URL, not the session: `config/routes.yaml` imports every controller route once per locale, so
  `app_home` exists as `app_home.ro` at `/` and `app_home.ru` at `/ru`. Never put a locale prefix or parameter in a
  `#[Route]` path. The backoffice is the one exception and does it explicitly — `/{_locale}/admin` — because EasyAdmin's
  language switcher works by rewriting that route parameter; see `src/Controller/Admin/AGENTS.md`.
- Catalogs are `translations/messages.<locale>.yaml` and may not fall back to English — the required strings in
  `legal.md` are the English originals, the RO/RU renderings ship. A new language is one `enabled_locales` entry, a
  `messages.<code>.yaml`, and its endonym under `locale.name.<code>` in both catalogs.
- Constraint messages are a domain of their own: they belong in `translations/validators.<locale>.yaml`, not in the
  messages catalogs. A key that only exists in `messages.<locale>.yaml` renders as the raw key inside a form error, which
  is how `reset.password.too_short` first shipped.

## Accounts and roles

Registration is the only way an account is born. It mails a generated password and confirmation link; the reset flow is
the only time the account holder chooses a password. Plain registration passwords and confirmation tokens exist only in
that mail — never log or store them.

Roles are cumulative and granted from the console. `SUPERADMIN_EMAIL` receives `ROLE_SUPER_ADMIN` only after confirming
the address, never at registration. Protected paths must handle both locales. Implementation details live in
`src/AGENTS.md`; backoffice rules live in `src/Controller/Admin/AGENTS.md`.

## Local stack (Docker)

No PHP on the host; everything runs in the `php` service. `make up` serves https://127.0.0.1:8000 (TLS only, self-signed
cert — accept the warning), `make down` stops it. Inside the compose network the site is `https://nginx`.

- `make shell CMD="php bin/console …"` runs one command there; bare `make shell` opens a shell (Alpine, so `sh`). `bin/*`
  is not executable on the bind mount — always prefix with `php`.
- e2e is these same files as a second compose project, `bloodbark-e2e`: `compose.e2e.yaml` sets `APP_ENV=test` (PHP-FPM
  runs `clear_env = no`, so the container env reaches the app) and nginx does not publish 8000. Own network, own
  `database_data` volume, untouched by `make down`. `make ee-up` / `ee` / `ee-down` start, use and stop it. Raw form:
  `docker compose -p bloodbark-e2e -f compose.yaml -f compose.e2e.yaml …`.
- The image carries headless Chromium + chromedriver for the acceptance suite and `ext-zip` (Codeception's WebDriver
  client needs it). Chromium won't start without a writable `HOME` — the only reason `HOME=/tmp` is set on the `php`
  service.
- `database` is PostgreSQL 18, data in `database_data` mounted at `/var/lib/postgresql` (the image keeps `PGDATA` under
  `/var/lib/postgresql/18/docker`); `php` waits on its healthcheck. Published to the host only by `compose.override.yaml`
  — change the port there, not in `compose.yaml`. From the host: `docker compose exec database psql -U bloodbark bloodbark`.
- `DATABASE_URL` in `.env` is dev-only (`database:5432`, user, password and database all `bloodbark`), hence committed.
  `make db-create` adds `bloodbark_test` (Doctrine's `dbname_suffix` under `when@test`) for PHPUnit; the e2e suites have
  their own database inside the e2e stack. Server version pinned to 18 in `config/packages/doctrine.yaml`.
- Logs: `docker compose logs -f php nginx`. Xdebug waits on port 9002, `PHP_IDE_CONFIG=serverName=bloodbark`, project
  root mounted at `/home/docker/src`.
- Certs are dev-only and git-ignored. Regenerate with
  `openssl req -x509 -nodes -days 365 -newkey rsa:2048 -keyout docker/nginx/certs/selfsigned.key -out docker/nginx/certs/selfsigned.crt -subj "/CN=localhost"`.
- `mailer` is Mailpit, declared in `compose.override.yaml` so only the dev stack runs it: inbox http://localhost:8025,
  SMTP `mailer:1025` on the compose network (what `.env.dev` puts in `MAILER_DSN`). `.env` keeps the null transport and
  `config/packages/mailer.yaml` pins it again under `when@test`, so neither `make unit` nor `make ee` sends anything. A
  real sender (e.g. Mailtrap's sandbox) goes in `.env.dev.local`, git-ignored, which loads after `.env.dev`. One test
  message: `make shell CMD="php bin/console mailer:test you@example.com"`.

## Everyday workflow

- When something fails, read `var/log/dev.log` and `/_profiler` before changing code.
- `maker-bundle` is installed: pass every argument up front and `--no-interaction` where supported — makers prompt on a
  terminal and hang a non-interactive shell. Hand-write the code if a maker still needs input.
- Schema changes go through migrations: `bin/console make:migration`, applied by `make migrate`. Never
  `doctrine:schema:update` or hand-written SQL. `App\Entity` maps with the attribute driver, so entities are plain
  classes in `src/Entity`.
- `.env` is committed and holds defaults only. Real secrets belong in `.env.local` (git-ignored) or the secrets vault
  (`bin/console secrets:set`), read via `%env(...)%`.

## Working safely

- **No `rm -rf` anywhere in this tree.** Move aside — `mv <path> <path>.trash-$(date +%s)` — and purge later. A mistaken
  `rm -rf .git` once deleted this repository's history; the working tree survived, the commits did not.
- Scratch work happens outside the project, in `mktemp -d`. Never `git init` in a subdirectory of this working copy.
- Destructive commands name their target and show it first: `pwd`, then `ls -d <path>`, then the command with an
  absolute path. No relative-path navigation into a delete.
- **No git hooks.** Formatting, linting, tests and any deploy step are commands someone runs on purpose (`make lint`,
  `make unit`), never a hook hiding in `.git/`. Do not add one.

## Testing

Run `make unit`; `make ee` is the end-to-end gate. A feature isn't done until a test exercises it the way a caller would.
Detailed setup and suite rules live in `tests/AGENTS.md`.

## Checks: style and static analysis

`make lint` runs php-cs-fixer, PHPStan, ESLint, and container/YAML/Twig lint. Use `composer cs:check` / `composer cs:fix`,
`composer phpstan`, or `composer check` for the PHP checks individually. A change isn't done while any check is red.

- php-cs-fixer uses `@Symfony` and checks `*.php` only, not `bin/console`. PHPStan is level 8 with strict and Doctrine
  rules plus `checkUninitializedProperties` over `src/` and `tests/`. Warm the dev cache if its container dump is missing;
  widen `paths` as directories appear and exclude generated code. Don't lower the level or add inline ignores — use
  `--generate-baseline` if an upgrade needs it.
- `make lint` and `make unit` set `XDEBUG_MODE=off`; use the same setting for raw PHP commands.

## CI

The required `lint` and `unit` status checks run on pull requests to `main`. Details live in `.github/AGENTS.md`.

## Discover, don't guess

Inspect the project and installed `vendor/` source rather than guessing: `bin/console about`, `debug:router`,
`debug:container`, `debug:config`, and `config:dump-reference`. Use the Symfony docs matching `composer.json`.
