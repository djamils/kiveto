<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Notification;

use App\Presentation\Clinic\ViewModel\Notification\BellReader;
use App\Shared\Application\Bus\CommandBusInterface;
use App\System\Notification\Application\Command\MarkAllInAppNotificationsRead\MarkAllInAppNotificationsRead;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * « Tout marquer lu »: every entry of the member in the current clinic.
 */
#[Route(path: '/notifications/read-all', name: 'clinic_notifications_read_all', methods: ['POST'])]
final class MarkAllInAppNotificationsReadController extends AbstractController
{
    public function __construct(
        private readonly BellReader $bell,
        private readonly CommandBusInterface $commandBus,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(MarkInAppNotificationReadController::CSRF_ID, $request->request->getString('_token')))) {
            return new JsonResponse(['error' => 'Token de sécurité invalide. Veuillez réessayer.'], JsonResponse::HTTP_FORBIDDEN);
        }

        [$userId, $clinicId] = $this->bell->owner();

        $this->commandBus->dispatch(new MarkAllInAppNotificationsRead($userId, $clinicId));

        return new JsonResponse(['unread' => 0]);
    }
}
