<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Scheduling;

use App\Scheduling\Application\Command\CreateWaitingRoomWalkInEntry\CreateWaitingRoomWalkInEntry;
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

        $data = $request->request->all();

        try {
            $this->commandBus->dispatch(new CreateWaitingRoomWalkInEntry(
                clinicId: $currentClinicId->toString(),
                ownerId: $data['ownerId'] ?? null,
                animalId: $data['animalId'] ?? null,
                foundAnimalDescription: $data['foundAnimalDescription'] ?? null,
                arrivalMode: $data['arrivalMode'] ?? 'STANDARD',
                priority: (int) ($data['priority'] ?? 0),
                triageNotes: $data['triageNotes'] ?? null,
            ));

            $this->addFlash('success', 'Entrée walk-in créée avec succès.');

            return $this->redirectToRoute('clinic_scheduling_dashboard');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la création : ' . $e->getMessage());

            return $this->redirectToRoute('clinic_scheduling_dashboard');
        }
    }
}
