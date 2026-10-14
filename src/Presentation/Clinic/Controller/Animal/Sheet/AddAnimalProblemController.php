<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Animal\Sheet;

use App\Context\Animal\Application\Command\AddMedicalAlert\AddMedicalAlert;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * A "problem" of the sheet is a medical alert of the animal: an allergy,
 * which the prescription check relies on, or a condition to keep in mind.
 */
#[Route('/animals/{id}/problems', name: 'clinic_animals_problem_add', methods: ['POST'])]
final class AddAnimalProblemController extends AbstractAnimalSheetAction
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
    ) {
    }

    public function __invoke(string $id, Request $request): Response
    {
        $this->guard($request);

        $animal = $this->animal($id);

        if (!$this->isOpen($animal)) {
            return $this->refuseClosedFile($id);
        }

        $added = $this->attempt(fn () => $this->commandBus->dispatch(new AddMedicalAlert(
            clinicId: $animal->clinicId,
            animalId: $animal->id,
            kind: $request->request->getString('kind', 'CHRONIC_CONDITION'),
            label: $request->request->getString('label'),
            note: $this->trimmed($request, 'note'),
        )));

        if ($added) {
            $this->addFlash('success', 'Problème ajouté au dossier.');
        } else {
            $this->addFlash('error', 'Le problème n’a pas pu être ajouté : l’intitulé est obligatoire et ne doit pas déjà figurer au dossier.');
        }

        return $this->backToSheet($id);
    }
}
