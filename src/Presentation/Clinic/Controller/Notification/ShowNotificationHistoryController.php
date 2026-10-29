<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Notification;

use App\Presentation\Clinic\ViewModel\Notification\BellReader;
use App\Presentation\Clinic\ViewModel\Notification\NotificationFeedBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * « Voir tout l'historique »: the bell entries of the last 30 days, by day —
 * the 200 most recent, with a note when there are more.
 */
#[Route(path: '/notifications/history', name: 'clinic_notifications_history', methods: ['GET'])]
final class ShowNotificationHistoryController extends AbstractController
{
    public const int LIMIT = 200;

    public function __construct(
        private readonly BellReader $bell,
        private readonly NotificationFeedBuilder $feed,
    ) {
    }

    public function __invoke(): Response
    {
        [$userId, $clinicId] = $this->bell->owner();

        // One more than shown, to know whether older entries are left out.
        $items     = $this->feed->items($userId, $clinicId, self::LIMIT + 1);
        $truncated = \count($items) > self::LIMIT;
        $days      = [];

        foreach (\array_slice($items, 0, self::LIMIT) as $item) {
            $days[$item->day][] = $item;
        }

        return $this->render('clinic/notifications/history.html.twig', [
            'days'      => $days,
            'truncated' => $truncated ? self::LIMIT : null,
            'unread'    => $this->feed->unread($userId, $clinicId),
        ]);
    }
}
