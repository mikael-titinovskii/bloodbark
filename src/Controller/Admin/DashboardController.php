<?php

namespace App\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;

use function Symfony\Component\Translation\t;

/**
 * The backoffice entry point. The `#[AdminDashboard]` attribute is what EasyAdmin's route loader
 * reads: it creates the dashboard route and prefixes every CRUD route with it, so `config/routes.yaml`,
 * which imports controller routes once per locale, never sees this route.
 *
 * The locale sits in the path here — the one place in the app that does it — because EasyAdmin's
 * language switcher works by rewriting the `_locale` route parameter; a route without it would
 * render one language and ignore the switch. That is also why `/admin` alone no longer matches:
 * `App\EventListener\AdminLandingRedirect` sends it to the default locale.
 *
 * Access is `access_control` in security.yaml, not a firewall: the account is an ordinary account
 * that happens to hold ROLE_SUPER_ADMIN.
 */
#[AdminDashboard(
    routePath: '/{_locale}/admin',
    routeName: 'app_admin',
    routeOptions: ['requirements' => ['_locale' => self::LOCALE_PATTERN]],
)]
final class DashboardController extends AbstractDashboardController
{
    /**
     * Mirrors `framework.enabled_locales`, which cannot be read from an attribute argument (those
     * have to be constant expressions). Adding a language means this constant, that setting, and a
     * `messages.<code>.yaml`.
     */
    private const string LOCALE_PATTERN = 'ro|ru';

    /**
     * @param list<string> $enabledLocales
     */
    public function __construct(
        private readonly AdminUrlGeneratorInterface $adminUrlGenerator,
        #[Autowire('%kernel.enabled_locales%')] private readonly array $enabledLocales,
    ) {
    }

    /**
     * There is nothing to summarise: the only thing a super admin comes here to do is moderate
     * accounts, so the dashboard is the account list.
     */
    public function index(): Response
    {
        return $this->redirect($this->adminUrlGenerator->setController(UserCrudController::class)->generateUrl());
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            // The brand name is the one string that is not translated; everything else in the menu
            // comes from the `messages` catalogs like the rest of the site.
            ->setTitle('Bloodbark')
            // Renders the language list in EasyAdmin's settings dropdown, from the same list the
            // public site serves — so a language added there appears here without a second edit.
            ->setLocales($this->enabledLocales);
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::section(t('admin.menu.moderation'));
        // EasyAdmin 5 names this `linkTo()`; 4.x's `linkToCrud()` is gone.
        yield MenuItem::linkTo(UserCrudController::class, t('admin.menu.users'), 'internal:user');
    }
}
