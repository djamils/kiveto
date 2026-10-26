<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Notification;

use App\Presentation\Clinic\ViewModel\Notification\BellReader;
use App\Presentation\Clinic\ViewModel\Notification\NotificationFeedBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The bell: the unread count (polled every minute, `?count`) and, when the
 * dropdown opens, its 20 latest entries rendered with the shell's markup.
 */
#[Route(path: '/notifications', name: 'clinic_notifications_list', methods: ['GET'])]
final class ListInAppNotificationsController extends AbstractController
{
    public function __construct(
        private readonly BellReader $bell,
        private readonly NotificationFeedBuilder $feed,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        [$userId, $clinicId] = $this->bell->owner();

        $payload = ['unread' => $this->feed->unread($userId, $clinicId)];

        if (!$request->query->has('count')) {
            $payload['html'] = $this->renderView('components/layout/_notification_items.html.twig', [
                'items' => $this->feed->items($userId, $clinicId),
            ]);
        }

        return new JsonResponse($payload);
    }
}
