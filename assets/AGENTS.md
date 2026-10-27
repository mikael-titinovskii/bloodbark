# Front-end build

Plain Vite — no Symfony bundle, no Asset component, no manifest. Sources in `assets/`, build output at fixed filenames
in `public/assets/`, templates link by name.

- `make assets` builds, `make assets-watch` rebuilds on change, `make npm CMD="…"` runs any npm command. All three shell
  into the `node` service — npm never runs on the host (Windows-accessible checkout: lifecycle scripts fail).
  `node_modules` is ignored; the cache goes to `var/npm-cache`. The service carries the `tools` profile, so `make up`
  and `make ee-up` skip it; `docker compose run --rm node …` enables it implicitly.
- Entries are declared by hand in `vite.config.mjs`, keyed by output path: `app` → `/assets/app.js`, `'pages/home'` →
  `/assets/pages/home.js`. To add one, drop a file under `assets/`, add it to `input`, reference it from a template.
  `templates/home/index.html.twig` is the pattern — it calls `{{ parent() }}` first so the shared `app.js` stays.
- Tailwind CSS v4 is wired in through `@tailwindcss/vite` (a plugin in `vite.config.mjs`), not a PostCSS config. The
  stylesheet is `assets/styles/app.css`, imported by the shared `assets/app.js` entry and built to `/assets/app.css`,
  which `templates/base.html.twig` links from its `stylesheets` block — Vite emits CSS as a separate asset, it is not
  injected by the module at runtime, so a new entry that imports CSS needs its own `<link>`.
- That stylesheet uses `@import 'tailwindcss' source(none)` and declares exactly what is scanned: `templates/`, `src/`,
  `assets/**/*.js`. Automatic content detection is off on purpose (the build runs in a container against a bind mount, so
  the inferred project root isn't reliable). A class that lives somewhere else — a new top-level directory, a package's
  Twig templates — needs an `@source` line or Tailwind will not emit it. Utilities are compiled only for classes that
  appear literally in those files; there is no safelist.
- `make assets-watch` is the Twig loop: the plugin registers every scanned file as a watch file, so editing a template,
  PHP file or JS module rebuilds `app.css` in well under a second through the bind mount (no polling needed). Two limits:
  a *new* file triggers nothing (nothing registered it), and candidates accumulate only within one watcher session, so a
  removed class lingers until restart. After adding a template or creating/deleting an `@source` directory, restart the
  watcher — or run `make assets`, which scans from scratch.
- Filenames carry no content hash on purpose, so templates can name them and no manifest lookup is needed. Nothing sets
  long cache headers for `/assets/`, so browsers revalidate rather than serve a stale bundle — keep it that way unless
  hashing is introduced. `public/assets/` is build output only; Vite empties it on each build.
- `npm run lint` (part of `make lint`) runs ESLint over `assets/` and the config files via `eslint.config.js`: ESLint
  `recommended` plus browser globals, Node globals for the configs; `vendor/`, `node_modules/`, `public/`, `var/` are
  ignored. Fix with `make npm CMD="run lint -- --fix"`.
