<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Animal\Sheet;

use App\Context\Animal\Application\Command\RecordAnimalWeight\RecordAnimalWeight;
use App\Presentation\Clinic\ViewModel\Client\StaffNameResolver;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/animals/{id}/weights', name: 'clinic_animals_weight_record', methods: ['POST'])]
final class RecordAnimalWeightController extends AbstractAnimalSheetAction
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly StaffNameResolver $staffNameResolver,
    ) {
    }

    public function __invoke(string $id, Request $request): Response
    {
        $this->guard($request);

        $animal = $this->animal($id);

        if (!$this->isOpen($animal)) {
            return $this->refuseClosedFile($id);
        }

        // Typed in kilograms, with a comma or a dot; stored in grams.
        $raw   = str_replace([' ', ','], ['', '.'], $request->request->getString('weight'));
        $score = $this->trimmed($request, 'body_condition_score');

        if (1 !== preg_match('/^\d{1,4}(\.\d{1,3})?$/', $raw) || (float) $raw <= 0 || !$this->isDate($request->request->getString('measured_on'))) {
            $this->addFlash('error', 'Indiquez un poids en kilogrammes, par exemple 14,2, et une date valide.');

            return $this->backToSheet($id);
        }

        $recorded = $this->attempt(fn () => $this->commandBus->dispatch(new RecordAnimalWeight(
            clinicId: $animal->clinicId,
            animalId: $animal->id,
            weightGrams: (int) round((float) $raw * 1000),
            measuredOn: $request->request->getString('measured_on'),
            bodyConditionScore: null === $score ? null : (int) $score,
            authorName: $this->staffNameResolver->resolve($animal->clinicId, $this->getUser()),
        )));

        if ($recorded) {
            $this->addFlash('success', 'Pesée enregistrée.');
        } else {
            $this->addFlash('error', 'La pesée n’a pas pu être enregistrée : vérifiez le poids et la date, qui ne peut pas être future.');
        }

        return $this->backToSheet($id);
    }
}
