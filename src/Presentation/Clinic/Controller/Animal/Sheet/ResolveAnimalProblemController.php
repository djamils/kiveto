<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Animal\Sheet;

use App\Context\Animal\Application\Command\RemoveMedicalAlert\RemoveMedicalAlert;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/animals/{id}/problems/{alertId}/resolve', name: 'clinic_animals_problem_resolve', methods: ['POST'])]
final class ResolveAnimalProblemController extends AbstractAnimalSheetAction
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
    ) {
    }

    public function __invoke(string $id, string $alertId, Request $request): Response
    {
        $this->guard($request);

        $animal = $this->animal($id);

        if (!$this->isOpen($animal)) {
            return $this->refuseClosedFile($id);
        }

        $removed = $this->attempt(fn () => $this->commandBus->dispatch(new RemoveMedicalAlert(
            clinicId: $animal->clinicId,
            animalId: $animal->id,
            alertId: $alertId,
        )));

        if ($removed) {
            $this->addFlash('success', 'Problème retiré du dossier.');
        } else {
            $this->addFlash('error', 'Ce problème ne figure plus au dossier.');
        }

        return $this->backToSheet($id);
    }
}
