# CI: the `lint` and `unit` jobs

`.github/workflows/ci.yml` runs those two targets on `ubuntu-latest` for every pull request against `main` — nothing
else, no e2e and no deploy. It calls the real recipes instead of a copy of them: `make lint PHP= NODE=` and
`make unit PHP=` blank the two prefixes the Makefile otherwise points at the compose services, leaving the bare
`composer`/`php`/`npm` calls that `setup-php` and `setup-node` put on PATH. Edit a command list in the Makefile and CI
follows; there is nothing to keep in sync.

- The `lint` job runs without a database. PHPStan analyses DQL without connecting — checked against an unreachable
  `DATABASE_URL` — but it needs `php bin/console cache:warmup` first, because `phpstan.dist.neon` reads the dev
  container dump.
- The `unit` job runs a `postgres:18-alpine` service, then creates and migrates `bloodbark_test` (`dbname_suffix` under
  `when@test`) before `php bin/phpunit`. `DATABASE_URL` points at `127.0.0.1`, since `.env`'s `database` host only
  resolves inside compose. Tests seed their own rows, so there is no `doctrine:fixtures:load` step.
- Both jobs pin PHP 8.5 with the extensions `docker/php/Dockerfile` installs, minus the xdebug, redis and codeception
  parts; `XDEBUG_MODE=off` is set at job level to match what the Makefile passes locally.
- The job names `lint` and `unit` are the required status checks on `main`. Renaming one silently drops it from the
  ruleset, so rename it in both places.
