<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Scheduling\WaitingRoom;

use App\Context\Scheduling\Application\Command\CreateWaitingRoomWalkInEntry\CreateWaitingRoomWalkInEntry;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/scheduling/waiting-room/walk-in', name: 'clinic_scheduling_walkin_create', methods: ['POST'])]
final class CreateWalkInController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        try {
            // Convert empty strings to null for optional fields.
            $ownerId                = $request->request->getString('ownerId') ?: null;
            $animalId               = $request->request->getString('animalId') ?: null;
            $foundAnimalDescription = $request->request->getString('foundAnimalDescription') ?: null;
            $triageNotes            = $request->request->getString('triageNotes') ?: null;

            $this->commandBus->dispatch(new CreateWaitingRoomWalkInEntry(
                clinicId: $currentClinicId->toString(),
                ownerId: $ownerId,
                animalId: $animalId,
                foundAnimalDescription: $foundAnimalDescription,
                arrivalMode: $request->request->getString('arrivalMode', 'STANDARD'),
                priority: $request->request->getInt('priority'),
                triageNotes: $triageNotes,
            ));

            $this->addFlash('success', 'Entrée walk-in créée avec succès.');

            return $this->redirectToRoute('clinic_scheduling_agenda');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la création : ' . $e->getMessage());

            return $this->redirectToRoute('clinic_scheduling_agenda');
        }
    }
}
