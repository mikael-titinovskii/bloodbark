# Backoffice

`/{_locale}/admin` is EasyAdmin 5 (`easycorp/easyadmin-bundle`). It is an internal tool and does not share the public
design: EasyAdmin ships Bootstrap 5 and FontAwesome, the rest of the site stays Twig + Tailwind.

- `App\Controller\Admin\DashboardController` carries
  `#[AdminDashboard(routePath: '/{_locale}/admin', routeName: 'app_admin')]`; its `index()` redirects straight to the
  account list, which is the only thing a super admin comes here to do. `App\Controller\Admin\UserCrudController` is the
  only CRUD controller so far.
- The locale is a mandatory path segment here (`/ro/admin/user`, `/ru/admin/user`), unlike the rest of the site where
  Romanian is unprefixed — the price of a working language switcher: EasyAdmin rewrites the `_locale` route parameter,
  so the route must carry one. `configureDashboard()` feeds the switcher from `%kernel.enabled_locales%`; the route's
  `requirements` mirror that list in a constant, since attribute arguments can't read parameters.
- Bare `/admin` still works: `App\EventListener\AdminLandingRedirect` answers it before routing with a 302 to the
  default locale. `access_control` matches `^/(ro|ru)/admin`.
- Routes come from EasyAdmin's own loader (`config/routes/easyadmin.yaml`, `type: easyadmin.routes`), not from
  `config/routes.yaml`. The attribute creates the dashboard route; `app_admin_user_index`, `app_admin_user_edit`, … are
  generated from its path, locale placeholder included.
- `new` is disabled in `configureActions()` *and* refused in the controller: `Action::NEW` off only hides the button,
  EasyAdmin serves the route regardless, and an account that appears without a password nobody received is worse than
  no account. Everything else about an account comes from registration and confirmation.
- Properties with no setter (`roles`, `consentAt`, `confirmedAt`, `createdAt`) are hidden from the form, and the email is
  a disabled input — it is the login identifier and the confirmation record hangs off it.
- Field and menu labels are message keys (`Field::new('email', t('account.email'))`); EasyAdmin translates them in the
  `messages` domain, which is why `admin.*` lives in both catalogs. A label with no key renders as the raw key.
- `assets:install` (in composer's auto-scripts) publishes the bundle to `public/bundles/easyadmin`, which is git-ignored:
  a checkout that never ran `composer install` serves an unstyled backoffice.
- `tests/Controller/AdminTest.php` covers the three refusals (anonymous, plain user, moderator) and the account list for
  a super admin.
- `src/DataFixtures/SuperAdminFixtures.php` seeds `superadmin@bloodbark.test` with the password in its `PASSWORD`
  constant — a confirmed super admin, so `/admin` is reachable without registering, digging the generated password out
  of Mailpit and promoting yourself. Load with `doctrine:fixtures:load --append` (without it the command purges the dev
  database first); the fixture is a no-op when the row exists. It never reaches production —
  `DoctrineFixturesBundle` is `dev`/`test` only — and the e2e database gets the same account, which is what an admin
  acceptance test would sign in with.
