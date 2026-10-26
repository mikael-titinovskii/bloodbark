<?php

namespace App\EventListener;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * EasyAdmin's dashboard route carries the locale (`/{_locale}/admin`) because that is what its
 * language switcher switches; the price is that the bare `/admin` matches no route any more. This
 * keeps the old address working with one redirect to the default locale.
 *
 * It runs before the router — the request it answers is one that would otherwise 404 — and only
 * for that exact path, so `/admin/...` CRUD URLs are untouched and still go through
 * `access_control`.
 */
#[AsEventListener(event: RequestEvent::class, priority: 100)]
final class AdminLandingRedirect
{
    public function __construct(
        #[Autowire('%kernel.default_locale%')] private readonly string $defaultLocale,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || '/admin' !== rtrim($event->getRequest()->getPathInfo(), '/')) {
            return;
        }

        $event->setResponse(new RedirectResponse('/'.$this->defaultLocale.'/admin'));
    }
}
