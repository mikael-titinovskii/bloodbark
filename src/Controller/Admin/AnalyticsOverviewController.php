<?php

namespace App\Controller\Admin;

use App\Repository\AnalyticsEventRepository;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_SUPER_ADMIN')]
#[AdminRoute('/analytics', name: 'analytics')]
final class AnalyticsOverviewController extends AbstractController
{
    public function __construct(private readonly AnalyticsEventRepository $events)
    {
    }

    #[AdminRoute('/', name: 'overview', options: ['methods' => ['GET']])]
    public function index(): Response
    {
        $since = (new \DateTimeImmutable('-30 days'))->setTime(0, 0);

        return $this->render('admin/analytics/index.html.twig', [
            'total_events' => $this->events->count(),
            'unique_consents' => $this->events->countDistinctConsents(),
            'counts_by_name' => $this->events->countsByNameSince($since),
            'daily_counts' => $this->events->dailyCountsSince($since),
            'period_start' => $since,
        ]);
    }
}
