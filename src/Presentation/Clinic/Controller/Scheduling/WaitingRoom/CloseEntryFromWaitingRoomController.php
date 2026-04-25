<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Scheduling\WaitingRoom;

use App\Context\Scheduling\Application\Command\CloseWaitingRoomEntry\CloseWaitingRoomEntry;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    path: '/scheduling/waiting-room/{entryId}/close-from-queue',
    name: 'clinic_scheduling_waitingroom_close_from_queue',
    methods: ['POST'],
)]
final class CloseEntryFromWaitingRoomController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly Security $security,
    ) {
    }

    public function __invoke(Request $request, string $entryId): Response
    {
        if (!$this->isCsrfTokenValid('waiting_room_action', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        try {
            $currentUser = $this->security->getUser();
            $userId      = $currentUser?->getUserIdentifier();

            $this->commandBus->dispatch(new CloseWaitingRoomEntry(
                waitingRoomEntryId: $entryId,
                closedByUserId: $userId,
            ));
        } catch (\Throwable $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('clinic_scheduling_waiting_room');
    }
}
