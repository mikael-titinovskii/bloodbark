<?php

namespace App\Controller;

use App\Dto\Analytics\ConsentRequest;
use App\Dto\Analytics\EventRequest;
use App\Service\Analytics\AnalyticsConsentService;
use App\Service\Analytics\AnalyticsRecorder;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class AnalyticsController
{
    public function __construct(
        private readonly AnalyticsConsentService $consents,
        private readonly AnalyticsRecorder $recorder,
    ) {
    }

    #[Route('/analytics/consent', name: 'app_analytics_consent', methods: ['POST'])]
    public function consent(#[MapRequestPayload] ConsentRequest $choice): Response
    {
        try {
            $this->consents->saveChoice($choice);
        } catch (\InvalidArgumentException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage(), $exception);
        }

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route('/analytics/events', name: 'app_analytics_event', methods: ['POST'])]
    public function event(#[MapRequestPayload] EventRequest $event): Response
    {
        try {
            $this->recorder->record($event->name, $event->properties);
        } catch (\InvalidArgumentException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage(), $exception);
        }

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
