<?php

namespace App\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/**
 * One hit per request on the flows that cost something — mail, mostly.
 *
 * The key is the client IP rather than the account: the point is to stop a loop, and a loop does
 * not care whose address it types. The limits themselves live in `framework.rate_limiter`, so this
 * class only decides what is limited and what a refusal looks like (429 + Retry-After, which the
 * error page renders through the site chrome).
 */
final readonly class RequestThrottle
{
    public function __construct(
        #[Autowire(service: 'limiter.registration')] private RateLimiterFactory $registration,
        #[Autowire(service: 'limiter.password_reset')] private RateLimiterFactory $passwordReset,
        #[Autowire(service: 'limiter.email_confirmation')] private RateLimiterFactory $emailConfirmation,
    ) {
    }

    public function registration(Request $request): void
    {
        $this->consume($this->registration, $request);
    }

    public function passwordReset(Request $request): void
    {
        $this->consume($this->passwordReset, $request);
    }

    public function confirmation(Request $request): void
    {
        $this->consume($this->emailConfirmation, $request);
    }

    private function consume(RateLimiterFactory $factory, Request $request): void
    {
        $limit = $factory->create((string) $request->getClientIp())->consume();

        if ($limit->isAccepted()) {
            return;
        }

        throw new TooManyRequestsHttpException(max(0, $limit->getRetryAfter()->getTimestamp() - time()), 'Too many requests from this address.');
    }
}
