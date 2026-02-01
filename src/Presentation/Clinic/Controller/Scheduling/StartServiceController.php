<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Scheduling;

use App\Scheduling\Application\Command\StartServiceForWaitingRoomEntry\StartServiceForWaitingRoomEntry;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\SecurityBundle\Security;

#[Route('/scheduling/waiting-room/{entryId}/start-service', name: 'clinic_scheduling_waitingroom_start', methods: ['POST'])]
final class StartServiceController extends AbstractController
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

            $this->commandBus->dispatch(new StartServiceForWaitingRoomEntry(
                waitingRoomEntryId: $entryId,
                serviceStartedByUserId: $userId,
            ));

            $this->addFlash('success', 'Service démarré.');

            return $this->redirectToRoute('clinic_scheduling_dashboard');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur : ' . $e->getMessage());

            return $this->redirectToRoute('clinic_scheduling_dashboard');
        }
    }
}
