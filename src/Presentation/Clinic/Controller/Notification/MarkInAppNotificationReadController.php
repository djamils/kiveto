<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Notification;

use App\Presentation\Clinic\ViewModel\Notification\BellReader;
use App\Presentation\Clinic\ViewModel\Notification\NotificationFeedBuilder;
use App\Shared\Application\Bus\CommandBusInterface;
use App\System\Notification\Application\Command\MarkInAppNotificationRead\MarkInAppNotificationRead;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * A click on a bell entry marks it read (only the member's own entry), then
 * the shell opens its screen.
 */
#[Route(path: '/notifications/{id}/read', name: 'clinic_notifications_read', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
final class MarkInAppNotificationReadController extends AbstractController
{
    public const string CSRF_ID = 'clinic_notifications';

    public function __construct(
        private readonly BellReader $bell,
        private readonly NotificationFeedBuilder $feed,
        private readonly CommandBusInterface $commandBus,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    public function __invoke(string $id, Request $request): JsonResponse
    {
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(self::CSRF_ID, $request->request->getString('_token')))) {
            return new JsonResponse(['error' => 'Token de sécurité invalide. Veuillez réessayer.'], JsonResponse::HTTP_FORBIDDEN);
        }

        [$userId, $clinicId] = $this->bell->owner();

        $this->commandBus->dispatch(new MarkInAppNotificationRead($id, $userId));

        return new JsonResponse(['unread' => $this->feed->unread($userId, $clinicId)]);
    }
}
