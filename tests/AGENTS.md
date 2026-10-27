# Testing

`symfony/test-pack`: PHPUnit 13, `phpunit.dist.xml`, `tests/bootstrap.php`, the `test` environment. HTTP tests extend
`WebTestCase`, service tests `KernelTestCase`; run `make unit`. A feature isn't done until a test exercises it the way a
caller would — an HTTP request for a controller, a service call for a service, not just "it didn't throw". Tests use
PostgreSQL too (`bloodbark_test`, via Doctrine's `dbname_suffix`): run `make db-create` once and apply migrations with
`php bin/console doctrine:migrations:migrate --env=test`.

Rate-limiter counters live in `cache.rate_limiter`, which outlives a single test, so a functional test that submits a
throttled form clears it through `App\Tests\Support\ClearsRateLimits`; otherwise the suite throttles itself instead of
the app. That pool's namespace is derived from the container class, which is why `TooManyRequestsPageTest` (debug off)
clears it through a non-debug kernel.

## End-to-end: Codeception suites

`make ee` is the gate. Codeception (root `codeception.yml`, suites in `tests/<Suite>.suite.yml`) runs two suites against
the e2e stack, over nginx → PHP-FPM → its own database:

- `Smoke` (`tests/Smoke`, `PhpBrowser`): plain HTTP, no browser — "is it up, does it answer 200" belongs here.
  `tests/Smoke/HomepageCest.php` is the homepage check.
- `Acceptance` (`tests/Acceptance`, `WebDriver`): headless Chromium in the e2e `php` container, for real interaction.
  Codeception's `RunProcess` extension starts and stops chromedriver around the suite.

`PhpBrowser` and `WebDriver` can't share a suite (the WebDriver module conflicts with any other `Web` module), which is
why there are two. Both are HTTP end-to-end; neither uses an in-process kernel.

Database lifecycle is why `ee` is a single command:

- `ee-up` starts the stack and rebuilds the database every invocation — `database:drop --force --if-exists`, `create`,
  `migrations:migrate --allow-no-migration` (an empty `migrations/` must not fail the run), then `fixtures:load --append`
  (nothing to purge on a fresh schema; purge-with-truncate trips over foreign keys) — on `bloodbark_test` inside the e2e
  server. `make ee` is `ee-up` + `codecept run`, `make ee-down` stops it.
- e2e data comes from `src/DataFixtures` (`AppFixtures`), picked up because `DoctrineFixturesBundle` is registered for
  `dev` and `test`. Add entities there, not to the tests, so a suite can write freely and every run starts the same.
- `ee-up` also runs `cache:clear` for `test`: `var/cache/test` is shared with `make unit`, and a container compiled
  before an edit would otherwise survive into the run.
- `E2E_BASE_URL` (from `compose.yaml`, `https://nginx`) can be overridden in the environment. One suite directly:
  `docker compose -p bloodbark-e2e -f compose.yaml -f compose.e2e.yaml exec -T php php vendor/bin/codecept run Smoke`
  (`--debug` gives step-by-step requests, headers and status codes).
- Actor classes (`tests/Support/*Tester.php`, `_generated/`) come from `codecept build`; `codecept run` regenerates them.
  Both are git-ignored and excluded from php-cs-fixer and PHPStan — do not hand-format them. Failures leave a screenshot
  (browser suite only) and the response HTML in `var/codeception/`.
- Keep PHPUnit for controller/service work: Codeception is the outermost gate, not a replacement.

## Static analysis

`make lint` runs `codecept build` before PHPStan for a reason: the Cest files call methods that exist only on the actor
traits written into `tests/Support/_generated`, so on a checkout that never ran `make ee` PHPStan reports all of them as
undefined. That output stays git-ignored and excluded from both PHPStan and php-cs-fixer; regenerating it is harmless.
