<?php

namespace App\EventListener;

use App\Security\RequestThrottle;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;

#[AsEventListener(event: ControllerEvent::class)]
final class AnalyticsRequestGuard
{
    private const int MAX_BODY_BYTES = 8192;

    public function __construct(private readonly RequestThrottle $throttle)
    {
    }

    public function __invoke(ControllerEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $route = $request->attributes->get('_canonical_route', $request->attributes->get('_route'));

        if ('app_analytics_consent' === $route) {
            $this->throttle->analyticsConsent($request);
        } elseif ('app_analytics_event' === $route) {
            $this->throttle->analyticsEvent($request);
        } else {
            return;
        }

        if (strlen($request->getContent()) > self::MAX_BODY_BYTES) {
            throw new HttpException(413, 'The analytics payload is too large.');
        }
    }
}
