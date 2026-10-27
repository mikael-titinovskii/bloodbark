# Templates

- Pages extend `templates/base.html.twig`, which carries the site-wide scope banner — new pages must not re-add it.
  Error pages live in `templates/bundles/TwigBundle/Exception/` (`error404.html.twig`, `error.html` as the fallback for
  any other status) so the bundle's renderer picks them up; Twig only uses them with `debug: false`, so
  `tests/Controller/ErrorPageTest.php` boots a non-debug kernel to test them.
- The layout owns the chrome: header, the single `<main>` (centred column, `id="main"` for the skip link) and the footer,
  which holds the emergency notice, scope statement and cookie-preferences button so every page — error pages and empty
  states included — shows them without a page template repeating them. A page fills `{% block body %}` with content only
  — no `<main>`, no outer container — so every page inherits the same width and spacing. The layout also includes
  `analytics/_config.html.twig`, which supplies both locales' translated consent text and the localized API endpoints.
- Styling is Tailwind utilities written in the Twig, built from `assets/styles/app.css` by `make assets`; `make
  assets-watch` rebuilds as templates change. Forms render through `templates/form/tailwind.html.twig`
  (`twig.form_themes` in `config/packages/twig.yaml`), which carries the control, label, help and error classes, so a
  `form_row()` adds no classes of its own and a field's own `attr.class` still wins. See `assets/AGENTS.md` for the Vite
  and Tailwind build rules.
- Generate URLs with the unsuffixed route name — `path('app_home')` follows the current locale. Pass `_locale` to address
  another one, as the switcher in `base.html.twig` does.
- Every user-facing string goes through `|trans`; the brand name is the only untranslated text in a template.
