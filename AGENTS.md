# Project

**Role:** information and matching platform connecting dog owners. Not a veterinary service. Not an emergency service.
Not a broker of biological material.

Legal anchors, settled decisions, hard prohibitions, the closed listing schema, copy rules, required strings and the
escalation list live in `legal.md`. Read it before adding a feature, a field, a label or a page.

## Symfony

- Framework metadata goes in PHP attributes, never YAML/XML: `#[Route]`, `#[MapRequestPayload]`, `#[MapQueryString]`,
  `#[Assert\...]`, `#[AsCommand]`, `#[AsEventListener]`, `#[AsMessageHandler]`, `#[AsAlias]` / `#[AsTaggedItem]` /
  `#[Autoconfigure]`. Autowire and autoconfigure; use `#[Autowire]` / `#[Target]` in the class when a type-hint can't
  express it. A YAML service definition is the last resort.
- Controllers stay thin: bind request data, call a service, return a response. Business rules, query building,
  transactions and persistence belong in services.
- Use the components instead of hand-rolling: Form, Validator, Serializer, Messenger, Security (voters,
  authenticators), Twig `path()` / `url()`, and `symfony/lock`'s `LockFactory` for mutual exclusion. Before writing
  locks, queues, caches, HTTP clients, mailers or schedulers — or adding a third-party library — check whether a Symfony
  component covers it.
- Prefer `#[MapRequestPayload]` / `#[MapQueryString]` over `json_decode()` or `SerializerInterface` by hand; install the
  packages rather than falling back to manual parsing.
- Constructor property promotion, `readonly` for DTOs and value objects. Don't mark a service `readonly` if it might
  become `lazy: true` — a lazy proxy can't extend a `readonly` class.
- Pages are Twig templates under `templates/` extending `templates/base.html.twig`, which carries the site-wide scope
  banner — new pages must not re-add it. Error pages live in `templates/bundles/TwigBundle/Exception/`
  (`error404.html.twig`, `error.html` as the fallback for any other status) so the bundle's renderer picks them up; Twig
  only uses them with `debug: false`, so `tests/Controller/ErrorPageTest.php` boots a non-debug kernel to test them.
- The layout owns the chrome: header, the single `<main>` (centred column, `id="main"` for the skip link) and the footer,
  which holds the emergency notice and the scope statement so every page — error pages and empty states included — shows
  both without a page template repeating them. A page fills `{% block body %}` with content only — no `<main>`, no outer
  container — so every page inherits the same width and spacing.
- Styling is Tailwind utilities written in the Twig, built from `assets/styles/app.css` into `public/assets/app.css` by
  `make assets`; `make assets-watch` rebuilds as templates change. Forms render through `templates/form/tailwind.html.twig`
  (`twig.form_themes` in `config/packages/twig.yaml`), which carries the control, label, help and error classes, so a
  `form_row()` adds no classes of its own and a field's own `attr.class` still wins.

## Languages

Romanian is the default locale, served without a prefix; Russian under `/ru`. `framework.enabled_locales` in
`config/packages/translation.yaml` is the single list — the switcher reads it through the `supported_locales` Twig
global.

- Locale lives in the URL, not the session: `config/routes.yaml` imports every controller route once per locale, so
  `app_home` exists as `app_home.ro` at `/` and `app_home.ru` at `/ru`. Never put a locale prefix or parameter in a
  `#[Route]` path.
- Generate URLs with the unsuffixed name — `path('app_home')` follows the current locale. Pass `_locale` to address
  another one, as the switcher in `base.html.twig` does.
- Every user-facing string goes through `|trans`; the brand name is the only untranslated text in a template. Catalogs
  are `translations/messages.<locale>.yaml` and may not fall back to English — the required strings in `legal.md` are
  the English originals, the RO/RU renderings ship. A new language is one `enabled_locales` entry, a
  `messages.<code>.yaml`, and its endonym under `locale.name.<code>` in both catalogs.
- Constraint messages are a domain of their own: they belong in `translations/validators.<locale>.yaml`, not in the
  messages catalogs. A key that only exists in `messages.<locale>.yaml` renders as the raw key inside a form error, which
  is how `reset.password.too_short` first shipped.

## Accounts and roles

Registration is the only way an account is born and it mails a generated password. The single exception is the reset
flow: a one-time link lets the account holder type a new password.

- `App\Entity\User` (`app_user`) holds the contact details, the roles and the consent record. `ContactType` and
  `ContactWindow` are enums under `src/Entity` implementing `TranslatableInterface`, so the form choices and the account
  page translate their own labels — `contact.type.*` / `contact.window.*` in both catalogs.
- `POST /register` binds `App\Form\RegistrationType` onto a fresh `User`, then `App\Service\RegistrationService` generates
  the password (`App\Security\PasswordGenerator`: 20 characters, no punctuation, no lookalikes), hashes it, records the
  consent with `app.legal.policy_version` and mails the plaintext once. The row is flushed only after the mail is away,
  so a failing transport cannot leave an account whose password nobody received. The plaintext exists only in that
  message — never log it, never store it.
- The sender address is `app.mailer.sender`, `no-reply@bloodbark.md` until the real domain is set (`MAILER_SENDER`
  overrides it). Bump `app.legal.policy_version` whenever the policy text changes: registrations store the version they
  accepted, and an unchanged number makes that record meaningless.
- Login is the firewall's `form_login` against `app_login` (`email`/`password` fields, `authenticate` CSRF token); logout
  is the framework-generated `_logout_main` route, posted with the `logout` token. `App\Security\UserProvider` replaces
  the entity provider, because a login form hands back whatever casing the visitor typed while addresses are stored
  lowercased.
- Every account is a `ROLE_USER`. `ROLE_MODERATOR` and `ROLE_SUPER_ADMIN` live in the same JSON column and — until an
  admin screen exists — are granted from the console: `php bin/console app:user:promote <email> moderator|super_admin`.
  The hierarchy in `security.yaml` makes them cumulative: super admin ⊃ moderator ⊃ user.
- `access_control` matches local paths by regex (`^/(ru/)?account`), since every controller route exists once per
  locale. New protected areas follow the same shape.
- `PasswordResetRequest` (`app_password_reset_request`) keeps only the SHA-256 of a link token, one live row per account,
  valid for `PasswordResetService::TTL_HOURS`. `POST /forgot-password` answers the same sentence for a known and an
  unknown address (the form must not enumerate accounts), and `GET /reset-password/{token}` only renders the form — the
  token is spent by the POST that saves the new password, because mail clients and scanners open links on their own. The
  changed hash also logs that account's other sessions out, since the firewall compares it with the one stored at login.
- Mail is the expensive part of both flows, so `POST /register` and `POST /forgot-password` spend one hit per submission
  through `App\Security\RequestThrottle`, keyed by client IP: five an hour each, declared in `framework.rate_limiter`.
  A refusal is a 429 carrying `Retry-After`, rendered by
  `templates/bundles/TwigBundle/Exception/error429.html.twig`. Login has its own ceiling in the firewall
  (`login_throttling`: 5 attempts per 15 minutes per account+IP, reset by a successful sign-in).

## Local stack (Docker)

No PHP on the host; everything runs in the `php` service. `make up` serves https://127.0.0.1:8000 (TLS only, self-signed
cert — accept the warning), `make down` stops it. Inside the compose network the site is `https://nginx`.

- `make shell CMD="php bin/console …"` runs one command there; bare `make shell` opens a shell (Alpine, so `sh`). `bin/*`
  is not executable on the bind mount — always prefix with `php`.
- e2e is these same files as a second compose project, `bloodbark-e2e`: `compose.e2e.yaml` sets `APP_ENV=test` (PHP-FPM
  runs `clear_env = no`, so the container env reaches the app) and stops nginx from publishing 8000. It has its own
  network and its own `database_data` volume, and `make down` doesn't touch it. `make ee-up` / `make ee` / `make ee-down`
  start, use and stop it. Raw form: `docker compose -p bloodbark-e2e -f compose.yaml -f compose.e2e.yaml …`.
- The image carries headless Chromium and chromedriver for the acceptance suite, plus `ext-zip` (Codeception's WebDriver
  client needs it). Chromium refuses to start without a writable `HOME`, hence `HOME=/tmp` on the `php` service — the
  only reason the variable is there.
- `database` is PostgreSQL 18 with its data in `database_data`, mounted at `/var/lib/postgresql` (the image keeps
  `PGDATA` under `/var/lib/postgresql/18/docker`); `php` waits on its healthcheck. Published to the host only by
  `compose.override.yaml` — change the port there, not in `compose.yaml`. From the host:
  `docker compose exec database psql -U bloodbark bloodbark`.
- `DATABASE_URL` in `.env` is dev-only (`database:5432`, user, password and database all `bloodbark`), hence committed.
  `make db-create` adds `bloodbark_test` (Doctrine's `dbname_suffix` under `when@test`) for PHPUnit; the e2e suites have
  their own database inside the e2e stack. Server version pinned to 18 in `config/packages/doctrine.yaml`.
- Logs: `docker compose logs -f php nginx`. Xdebug waits on port 9002, `PHP_IDE_CONFIG=serverName=bloodbark`, project
  root mounted at `/home/docker/src`.
- Certs are dev-only and git-ignored. Regenerate with
  `openssl req -x509 -nodes -days 365 -newkey rsa:2048 -keyout docker/nginx/certs/selfsigned.key -out docker/nginx/certs/selfsigned.crt -subj "/CN=localhost"`.
- `node` is only for JS work. It carries the `tools` profile, so `make up` and `make ee-up` leave it alone;
  `docker compose run --rm node …` enables the profile implicitly.
- `mailer` is Mailpit, declared in `compose.override.yaml` so only the dev stack runs it: the inbox is
  http://localhost:8025 and SMTP is `mailer:1025` on the compose network, which is what `.env.dev` puts in `MAILER_DSN`.
  `.env` keeps `MAILER_DSN` on the null transport and `config/packages/mailer.yaml` pins it again under `when@test`, so
  neither `make unit` nor `make ee` sends anything. A real sender — Mailtrap's sandbox, say
  `smtp://USERNAME:PASSWORD@sandbox.smtp.mailtrap.io:2525` — goes in `.env.dev.local`, which is git-ignored and loads
  after `.env.dev`. One test message: `make shell CMD="php bin/console mailer:test you@example.com"`.

## Front-end build

Plain Vite — no Symfony bundle, no Asset component, no manifest. Sources in `assets/`, build output at fixed filenames
in `public/assets/`, templates link by name.

- `make assets` builds, `make assets-watch` rebuilds on change, `make npm CMD="…"` runs any npm command. All three shell
  into the `node` service — npm never runs on the host (the checkout is on a Windows-accessible path, where npm can't
  run lifecycle scripts). `node_modules` is ignored; the cache goes to `var/npm-cache`.
- Entries are declared by hand in `vite.config.mjs`, keyed by output path: `app` → `/assets/app.js`, `'pages/home'` →
  `/assets/pages/home.js`. To add one, drop a file under `assets/`, add it to `input`, reference it from a template.
  `templates/home/index.html.twig` is the pattern — it calls `{{ parent() }}` first so the shared `app.js` stays.
- Tailwind CSS v4 is wired in through `@tailwindcss/vite` (a plugin in `vite.config.mjs`), not a PostCSS config. The
  stylesheet is `assets/styles/app.css`, imported by the shared `assets/app.js` entry and built to `/assets/app.css`,
  which `templates/base.html.twig` links from its `stylesheets` block — Vite emits CSS as a separate asset, it is not
  injected by the module at runtime, so a new entry that imports CSS needs its own `<link>`.
- That stylesheet uses `@import 'tailwindcss' source(none)` and declares exactly what is scanned: `templates/`, `src/`,
  `assets/**/*.js`. Automatic detection is off on purpose (the build runs against a bind mount in a container, so the
  project root it would infer is not worth relying on). A class that lives somewhere else — a new top-level directory,
  a package's Twig templates — needs an `@source` line or Tailwind will not emit it. Utilities are compiled only for
  classes that appear literally in those files; there is no safelist.
- `make assets-watch` is the Twig loop: the plugin registers every file it scanned as a watch file, so editing a
  template, PHP file or JS module rebuilds `app.css` in well under a second (verified through the bind mount — no
  polling config needed). Two limits: a *new* file never triggers a build, since nothing registers it, and candidates
  only accumulate within one watcher session, so a removed class lingers until it restarts. After adding a template,
  creating a directory under `@source`, or deleting one, restart the watcher — or just run `make assets`, which scans
  from scratch.
- Filenames carry no content hash on purpose, so templates can name them and no manifest lookup is needed. Nothing sets
  long cache headers for `/assets/`, so browsers revalidate rather than serve a stale bundle — keep it that way unless
  hashing is introduced. `public/assets/` is build output only; Vite empties it on each build.
- `npm run lint` (part of `make lint`) runs ESLint over `assets/` and the config files via `eslint.config.js`: ESLint
  `recommended` plus browser globals, Node globals for the configs; `vendor/`, `node_modules/`, `public/`, `var/` are
  ignored. Fix with `make npm CMD="run lint -- --fix"`.

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
- History has a second copy at `/home/mimit/backups/bloodbark.git`; nothing keeps it current. Refresh it from WSL
  (Windows Git rewrites the path):
  `wsl -d Ubuntu -e bash -lc 'git --git-dir=/home/mimit/backups/bloodbark.git remote update --prune'`. Do not delete,
  rewrite or "clean up" that mirror.
- Git runs on both sides of the `\\wsl.localhost` share: `core.filemode=false` and `.gitattributes` pinning `eol=lf` are
  deliberate (Windows can't set exec bits; global `core.autocrlf=true` would otherwise write CRLF into `bin/*`,
  `Dockerfile`, `*.sh`). The signing key is Windows-only, so commits from WSL need
  `git -c commit.gpgsign=false commit …`.

## Testing

`symfony/test-pack`: PHPUnit 13, `phpunit.dist.xml`, `tests/bootstrap.php`, the `test` environment. HTTP tests extend
`WebTestCase`, service tests `KernelTestCase`; run `make unit`. A feature isn't done until a test exercises it the way a
caller would — an HTTP request for a controller, a service call for a service, not just "it didn't throw". Tests use
PostgreSQL too (`bloodbark_test`, via Doctrine's `dbname_suffix`): run `make db-create` once and apply migrations with
`php bin/console doctrine:migrations:migrate --env=test`.

Rate-limiter counters live in `cache.rate_limiter`, which outlives a single test, so a functional test that submits a
throttled form clears it through `App\Tests\Support\ClearsRateLimits`; otherwise the suite throttles itself instead of
the app. That pool's namespace is derived from the container class, which is why `TooManyRequestsPageTest` (debug off)
clears it through a non-debug kernel.

### End-to-end: Codeception suites

`make ee` is the gate. Codeception (root `codeception.yml`, suites in `tests/<Suite>.suite.yml`) runs two suites against
the e2e stack, over nginx → PHP-FPM → its own database:

- `Smoke` (`tests/Smoke`, `PhpBrowser`): plain HTTP, no browser — "is it up, does it answer 200" belongs here.
  `tests/Smoke/HomepageCest.php` is the homepage check.
- `Acceptance` (`tests/Acceptance`, `WebDriver`): headless Chromium in the e2e `php` container, for real interaction.
  Codeception's `RunProcess` extension starts and stops chromedriver around the suite.

`PhpBrowser` and `WebDriver` can't share a suite (the WebDriver module conflicts with any other `Web` module), which is
why there are two. Both are HTTP end-to-end; neither uses an in-process kernel.

Database lifecycle is why `ee` is a single command:

- `ee-up` starts the stack and rebuilds the database every invocation: `database:drop --force --if-exists`, `create`,
  `migrations:migrate --allow-no-migration` (so an empty `migrations/` doesn't fail the run), then
  `fixtures:load --append` (nothing to purge on a fresh schema, and purge-with-truncate would trip over foreign keys).
  With `_test` appended it acts on `bloodbark_test` inside the e2e server. `make ee` is `ee-up` plus `codecept run`;
  `make ee-down` stops it.
- e2e data comes from `src/DataFixtures` (`AppFixtures`), picked up because `DoctrineFixturesBundle` is registered for
  `dev` and `test`. Add entities there, not to the tests, so a suite can write freely and every run starts the same.
- `ee-up` also runs `cache:clear` for `test`: `var/cache/test` is shared with `make unit`, and a container compiled
  before an edit would otherwise survive into the run.
- `E2E_BASE_URL` (from `compose.yaml`, `https://nginx`) can be overridden in the environment. One suite directly:
  `docker compose -p bloodbark-e2e -f compose.yaml -f compose.e2e.yaml exec -T php php vendor/bin/codecept run Smoke`
  — add `--debug` for step-by-step requests, headers and status codes.
- Actor classes (`tests/Support/*Tester.php`, `_generated/`) come from `codecept build`; `codecept run` regenerates them.
  Both are git-ignored and excluded from php-cs-fixer and PHPStan — do not hand-format them. Failures leave a screenshot
  (browser suite only) and the response HTML in `var/codeception/`.
- Keep PHPUnit for controller/service work: Codeception is the outermost gate, not a replacement.

## Checks: style and static analysis

`make lint` runs all of it; the composer scripts are there when only one is needed.

- `composer cs:check` (dry run with a diff) / `composer cs:fix` — php-cs-fixer, `@Symfony` ruleset in
  `.php-cs-fixer.dist.php`, `*.php` only, so `bin/console` is not checked.
- `composer phpstan` — level 8 over `src/` and `tests/`, configured in `phpstan.dist.neon`, with
  `phpstan/phpstan-strict-rules`, `phpstan/phpstan-doctrine` (so a property the ORM fills in counts as written, and a
  nullable property over a NOT NULL column is an error) and `checkUninitializedProperties`. The Symfony extension resolves services from
  `var/cache/dev/App_KernelDevDebugContainer.xml`, so warm the dev cache if that file is missing. Widen `paths` as
  directories appear; exclude generated code (today only `tests/Support/_generated`). Don't lower the level or add
  inline ignores — use `--generate-baseline` if an upgrade ever needs it.
- `composer check` — both, style first. `make lint` then also runs `npm run lint`, `lint:container`,
  `lint:yaml config/ translations/` and `lint:twig templates/`.

A change isn't done while any of them is red. `make lint` and `make unit` set `XDEBUG_MODE=off` (otherwise every PHP
process spends a second reaching for a debugger on port 9002); the raw form is
`docker compose exec -T -e XDEBUG_MODE=off php composer check`.

## Discover, don't guess

APIs change and training data goes stale — look things up in the project instead of relying on memory:
`bin/console about`; `debug:router`, `debug:container`, `debug:autowiring <name>`, `debug:config <bundle>`,
`config:dump-reference <bundle>`; `lint:container`, `lint:yaml config/`, `lint:twig templates/` to validate before
running; the installed source and docblocks under `vendor/`; docs at https://symfony.com/doc/current/ (switch to the
version matching `composer.json` if it differs).
