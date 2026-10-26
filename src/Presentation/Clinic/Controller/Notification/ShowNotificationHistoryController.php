<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Notification;

use App\Presentation\Clinic\ViewModel\Notification\BellReader;
use App\Presentation\Clinic\ViewModel\Notification\NotificationFeedBuilder;
use App\Presentation\Clinic\ViewModel\Notification\NotificationItem;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * « Voir tout l'historique »: every bell entry of the last 30 days, by day.
 */
#[Route(path: '/notifications/history', name: 'clinic_notifications_history', methods: ['GET'])]
final class ShowNotificationHistoryController extends AbstractController
{
    public function __construct(
        private readonly BellReader $bell,
        private readonly NotificationFeedBuilder $feed,
    ) {
    }

    public function __invoke(): Response
    {
        [$userId, $clinicId] = $this->bell->owner();

        $days = [];

        foreach ($this->feed->items($userId, $clinicId, null) as $item) {
            $days[$item->day][] = $item;
        }

        return $this->render('clinic/notifications/history.html.twig', [
            'days'   => $days,
            'unread' => \count(array_filter(array_merge([], ...array_values($days)), static fn (NotificationItem $i): bool => !$i->read)),
        ]);
    }
}
