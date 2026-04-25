<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Scheduling\WaitingRoom;

use App\Context\Scheduling\Application\Command\UpdateWaitingRoomTriage\UpdateWaitingRoomTriage;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    path: '/scheduling/waiting-room/{entryId}/triage',
    name: 'clinic_scheduling_waitingroom_triage',
    methods: ['POST'],
)]
final class UpdateTriageController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
    ) {
    }

    public function __invoke(Request $request, string $entryId): Response
    {
        if (!$this->isCsrfTokenValid('waiting_room_action', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $arrivalMode = $request->request->getString('arrivalMode', 'STANDARD');
        if (!\in_array($arrivalMode, ['STANDARD', 'EMERGENCY'], true)) {
            $arrivalMode = 'STANDARD';
        }

        $priority    = $request->request->getInt('priority', 0);
        $triageNotes = $request->request->getString('triageNotes') ?: null;

        try {
            $this->commandBus->dispatch(new UpdateWaitingRoomTriage(
                waitingRoomEntryId: $entryId,
                priority: $priority,
                triageNotes: $triageNotes,
                arrivalMode: $arrivalMode,
            ));
        } catch (\Throwable $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('clinic_scheduling_waiting_room');
    }
}
