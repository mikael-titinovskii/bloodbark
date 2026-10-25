# Project

**Role:** information and matching platform connecting dog owners. Not a veterinary service. Not an emergency service.
Not a broker of biological material.

**Legal anchors:** personal data — Law 195/2024 (GDPR-model, in force 23.08.2026); veterinary profession reserved — Law
121/2020; animal by-products regime attaches to acts on the material — Law 129/2019; e-commerce needs registration — Law
284/2004 art.9 (3);
abusive clauses void — Law 105/2003 art.5.

 ---

## Decisions already made — do not revisit

Settled with the owner. Do not "improve" on these, do not propose alternatives, do not implement a feature that requires
reversing them.

1. **Zero revenue, forever.** No ads, no fees, no paid promotion, no sponsorship, no donations of any kind, no payment
   processing integrated for any purpose. This keeps the project outside Law 284/2004 registration and outside
   consumer-contract exposure entirely.
2. **No custody of biological material.** Never store, transport, arrange transport for, handle, or hold blood or any
   sample. Connect owners only. The by-products regime attaches to acts on the material, not to introductions.
3. **Filter, never decide.** The user supplies the criterion. The system displays and filters declared facts. It never
   concludes applicability to any specific animal.
4. **Dogs only.** Cats deferred — feline alloantibodies make a first mismatch rapidly fatal.

If a request would cross one of these, stop and say so rather than building it.

 ---

## Never build

- Compatibility output: no "match", "compatible", "suitable", "safe", score, fit %, or ranking by fit. No exceptions, no
  soft phrasing that means the same thing.
- Clinical advice, dosage, procedure steps, or any instruction to transfuse.
- Any logic — AI or rules — concluding fitness for a specific recipient. AI extracts and displays fields only.
- Upload of medical documents, scans, invoices, lab PDFs. Blood type is one enumerated field.
- Public owner identity: no name, phone, email, address, or social handle in search output or any public page.
- Exact addresses. District or city only.
- Custody, storage, transport, or logistics features of any kind.
- Waiver or liability-exclusion clauses. No "entertainment purposes" or equivalent framing.
- Any revenue, donation, or payment feature.
- Scraped clinic or owner data.
- Donor ratings, donation leaderboards, or public comment threads about clinics or vets.
- Any field outside the schema below.
- General monitoring or proactive triage of user content — you are not the emergency pathway.

## Required before launch

- Explicit consent checkbox, logged with timestamp and policy version.
- Privacy policy and ToS in RO and RU.
- One-page processing register (ROPA).
- Working data export and deletion, self-serve or admin.
- Written breach plan: detect → assess → notify CNPDCP → inform affected users.
- Processor agreement with hosting and any AI provider, including no-training clause.
- Hosting in EU or MD; if elsewhere, transfer safeguards documented.
- Listing expiry with re-confirmation ping.
- Report button plus moderation action log.
- Age 18+ gate.
- Scope banner site-wide; emergency notice on search and on all empty states.

 ---

## Listing schema (closed set)

```

species · breed · weight_kg · age_years
blood_type        DEA 1.1 | 1.2 | 3 | 4 | 5 | 6 | 7 | Dal | unknown
blood_type_source vet_confirmed{clinic, date} | owner_reported
district · available_from · available_to · verification_state · expires_at

```

Owner contact lives in a separate record. Never joined into search output. Contact is relay-only, or vet-initiated where
the donor has opted in.

 ---

## Copy rules

Every label describes a property of the donor. Never a relation to the searcher's dog.

- OK: `DEA 1.1 negative donors · Chișinău · clinic-verified`
- BAD: `Matching donors for your dog`
- OK: `3 new listings match your saved filter`
- BAD: `We found donors we think will work for Rex`

Verification state visible on every listing. Unverified data must never look verified. Saved-search alerts are permitted
only as literal filter results, never as recommendations.

## Required strings

**Scope** (site-wide):
> We are an information and matching service. We do not provide veterinary advice, diagnosis, compatibility
> determinations, or transfusion services. All compatibility decisions and transfusions must be made by a licensed
> veterinarian.

**Emergency** (search + empty states):
> Not an emergency service. In an acute case, contact your veterinarian now.

**Empty state:** widen to neighbouring districts · save this search · contact your vet.

**Donor attestation:**
> I confirm a licensed veterinarian has assessed this animal as fit to donate and has determined its blood type.

 ---

## Escalate to a human, do not decide

- Any feature that would put the platform in custody, transport, or scheduling of the procedure.
- Any change to the revenue decision, including "temporary" or "test" monetisation.
- Any proposal to let the system rank, recommend, or score donors.
- Any request to add cats, add species, or add organ matching.

# Symfony

- Use PHP attributes for framework metadata, and not only on controllers:
  `#[Route]`, `#[MapRequestPayload]`, `#[IsGranted]` on actions, `#[Assert\...]`
  on properties, `#[AsCommand]`, `#[AsEventListener]`, `#[AsMessageHandler]`, and
  `#[AsAlias]` / `#[AsTaggedItem]` / `#[Autoconfigure]` on services. No YAML or
  XML routing.
- Rely on autowiring and autoconfiguration. Type-hint constructor arguments and
  let the container resolve them. Where a type-hint can't express it, stay in the
  class with `#[Autowire]` (parameters, env vars, expressions) or `#[Target]` (one
  of several implementations of an interface). A YAML service definition is the
  last resort, not the first.
- Controllers extend `AbstractController`, stay thin, and delegate to services.
  Put logic in services — business rules, query building, transactions,
  persistence, orchestration — and never grow it inside a controller. A bloated
  controller is a defect, not a style preference: an action should only bind
  request data, call a service, and return a response. If an action is growing
  past that, extract the missing service instead of adding another branch.
- Use the framework for what it already does: Form for server-rendered forms,
  Validator for validation, Serializer for JSON, Messenger for async work,
  Security (voters, authenticators) for access control, Twig `path()`/`url()`
  instead of hardcoded URLs.
- Before hand-writing infrastructure (locks, queues, caches, HTTP clients,
  mailers, schedulers) or reaching for a third-party library, check whether a
  Symfony component covers it. It usually does.

Three specifics worth spelling out, because they are easy to get wrong:

- Bind request data with `#[MapRequestPayload]` / `#[MapQueryString]` on action
  arguments, which wires up Serializer and Validator for you, instead of calling
  `json_decode()` or `SerializerInterface` by hand. If neither package is
  installed yet, `composer require` them rather than falling back to manual
  parsing.
- Use constructor property promotion, and `readonly` for DTOs and value objects.
  Don't mark a service `readonly` if it might become `lazy: true`: a lazy proxy
  can't extend a `readonly` class.
- Use `symfony/lock` (`LockFactory`) for mutual exclusion. A hand-built flag or
  lock file looks fine in review and is usually wrong under concurrency.

## Local stack (Docker)

PHP-FPM, nginx and the TLS certs live in `docker/` and are wired up by `compose.yaml`. There is no PHP on the host, so
run everything through the `php` service:

- `make up` — site at https://127.0.0.1:8000 (HTTPS only, self-signed cert: accept the warning).
  `make down` stops it. Both wrap `docker compose up -d` / `docker compose down`.
- `make ee CMD="php bin/console …"` runs one command in the `php` container; bare `make ee` opens a shell in it
  (the image is Alpine, so `sh`, not bash). `bin/*` is not executable on the bind mount — always prefix with `php`.
- The `database` service is PostgreSQL 18 (`postgres:18-alpine`) with its data in the `database_data` volume;
  `php` waits for its healthcheck. The image keeps `PGDATA` under `/var/lib/postgresql/18/docker`, so the volume is
  mounted at `/var/lib/postgresql`, not `/data`.
- It is published to the host only by `compose.override.yaml` (written by the Doctrine recipe): change the port there,
  not in `compose.yaml`. From the host: `docker compose exec database psql -U bloodbark bloodbark`.
- `DATABASE_URL` in `.env` points at `database:5432` with user, password and database all `bloodbark` — dev-only
  credentials, hence committed. The container creates `bloodbark`; `make db-create` adds `bloodbark_test` (the
  `_test` suffix comes from Doctrine's `dbname_suffix` under `when@test`). Server version is pinned to 18 in
  `config/packages/doctrine.yaml`.
- Logs: `docker compose logs -f php nginx`.
- Xdebug listens for the IDE on port 9002; `PHP_IDE_CONFIG=serverName=bloodbark`, project root mounted at
  `/home/docker/src`.
- The certs are dev-only and git-ignored. Regenerate with
  `openssl req -x509 -nodes -days 365 -newkey rsa:2048 -keyout docker/nginx/certs/selfsigned.key -out docker/nginx/certs/selfsigned.crt -subj "/CN=localhost"`.

## Everyday workflow

- When something fails, read `var/log/dev.log` and the web profiler (`/_profiler`) before changing code.
- `maker-bundle` is installed: prefer `bin/console make:*` with every argument
  passed up front and `--no-interaction` where supported: makers prompt on a
  terminal by default, which hangs a non-interactive shell. If a maker still
  needs interactive input, hand-write the code instead.
- Schema changes go through migrations: `bin/console make:migration` writes a class into `migrations/`,
  `make migrate` (or `doctrine:migrations:migrate`) applies it. Never
  `doctrine:schema:update` or hand-written SQL. `config/packages/doctrine.yaml` maps `App\Entity` with the attribute
  driver, so entities are plain classes in `src/Entity`.
- `.env` is committed and holds defaults only. Real secrets belong in `.env.local`
  (git-ignored) or the secrets vault (`bin/console secrets:set`), read via
  `%env(...)%`.

## Testing

`symfony/test-pack` is installed: PHPUnit 13 with `phpunit.dist.xml`, bootstrapped by
`tests/bootstrap.php` in the `test` environment. Functional/HTTP tests extend
`WebTestCase`; service-level tests extend `KernelTestCase`. Run `make unit` (it calls
`php bin/phpunit`). A feature isn't done
until it has a test that exercises it the way a caller would, an HTTP request for
a controller or a service call for a service, not just "it didn't throw."

Tests run against PostgreSQL too: the `test` environment renames the database to `bloodbark_test` (Doctrine's
`dbname_suffix`), so run `make db-create` once, and apply schema changes to it with
`php bin/console doctrine:migrations:migrate --env=test`.

## Checks: style and static analysis

`make lint` runs everything below; the individual composer scripts are there when
only one of them is needed:

- `composer cs:check`: php-cs-fixer dry run with a diff; `composer cs:fix` writes
  the fixes. The `@Symfony` ruleset (PSR-12-derived superset) is configured in
  `.php-cs-fixer.dist.php`. It only scans `*.php`, so `bin/console` is not checked.
- `composer phpstan`: PHPStan at level 8 over `src/`, configured in
  `phpstan.dist.neon`, with `phpstan/phpstan-strict-rules` and
  `checkUninitializedProperties` on. The Symfony extension (`phpstan/phpstan-symfony`,
  loaded by `phpstan/extension-installer`) resolves services from
  `var/cache/dev/App_KernelDevDebugContainer.xml`, so warm the dev cache first if
  that file is missing. Widen `paths` as directories appear — `tests` once it exists.
  Don't lower the level or add inline ignores to make a run pass; if a codebase-wide
  upgrade ever needs it, use `--generate-baseline`.
- `composer check`: both, style first. `make lint` also runs `lint:container` and
  `lint:yaml config/` after those two.

A change isn't done while any of them is red. `make lint` and `make unit` already set
`XDEBUG_MODE=off` (the compose stack otherwise spends a second per PHP process trying
to reach a debugger on port 9002); the raw form is
`docker compose exec -T -e XDEBUG_MODE=off php composer check`.

## Discover, don't guess

Framework APIs change between versions and your training data may be stale. Look
things up in the project instead of relying on memory:

- `bin/console about`: versions, environment, paths.
- `bin/console debug:router`, `debug:container`, `debug:autowiring <name>`,
  `debug:config <bundle>`, `config:dump-reference <bundle>`: what exists and how
  it is configured.
- `bin/console lint:container`, plus `lint:twig templates/` and
  `lint:yaml config/` where those packages are installed: validate before running.
- Read the installed source and docblocks under `vendor/`.
- Docs: https://symfony.com/doc/current/ (switch to the version matching
  `composer.json` if it differs).
