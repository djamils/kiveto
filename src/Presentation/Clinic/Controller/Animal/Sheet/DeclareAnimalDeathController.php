<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Animal\Sheet;

use App\Context\Animal\Application\Command\UpdateAnimalLifeCycle\UpdateAnimalLifeCycle;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/animals/{id}/death', name: 'clinic_animals_death_declare', methods: ['POST'])]
final class DeclareAnimalDeathController extends AbstractAnimalSheetAction
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

        $deceasedOn = $this->trimmed($request, 'deceased_on');

        // One day of slack: the clinic's "today" can be a day ahead of the server's.
        $latest = (new \DateTimeImmutable('tomorrow'))->format('Y-m-d');

        if (!$this->isDate($deceasedOn) || $deceasedOn > $latest) {
            $this->addFlash('error', 'Indiquez une date de décès valide, qui ne soit pas future.');

            return $this->backToSheet($id);
        }

        $declared = $this->attempt(fn () => $this->commandBus->dispatch(new UpdateAnimalLifeCycle(
            clinicId: $animal->clinicId,
            animalId: $animal->id,
            lifeStatus: 'deceased',
            deceasedAt: $deceasedOn,
            missingSince: null,
        )));

        if ($declared) {
            $this->addFlash('success', \sprintf('Le décès de %s est enregistré.', $animal->name));
        } else {
            $this->addFlash('error', 'Le décès n’a pas pu être enregistré : vérifiez la date.');
        }

        return $this->backToSheet($id);
    }
}
