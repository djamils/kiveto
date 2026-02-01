<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Scheduling;

use App\Scheduling\Application\Command\CloseWaitingRoomEntry\CloseWaitingRoomEntry;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\SecurityBundle\Security;

#[Route('/scheduling/waiting-room/{entryId}/close', name: 'clinic_scheduling_waitingroom_close', methods: ['POST'])]
final class CloseWaitingRoomEntryController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly Security $security,
    ) {
    }

    public function __invoke(string $entryId): Response
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        try {
            $currentUser = $this->security->getUser();
            $userId      = $currentUser?->getUserIdentifier();

            $this->commandBus->dispatch(new CloseWaitingRoomEntry(
                waitingRoomEntryId: $entryId,
                closedByUserId: $userId,
            ));

            $this->addFlash('success', 'Entrée fermée.');

            return $this->redirectToRoute('clinic_scheduling_dashboard');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur : ' . $e->getMessage());

            return $this->redirectToRoute('clinic_scheduling_dashboard');
        }
    }
}
