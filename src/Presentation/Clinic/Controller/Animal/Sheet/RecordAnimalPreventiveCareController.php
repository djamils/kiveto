<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Animal\Sheet;

use App\Context\Animal\Application\Command\RecordPreventiveCare\RecordPreventiveCare;
use App\Presentation\Clinic\ViewModel\Client\StaffNameResolver;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/animals/{id}/prevention', name: 'clinic_animals_preventive_care_record', methods: ['POST'])]
final class RecordAnimalPreventiveCareController extends AbstractAnimalSheetAction
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
            return $this->refuseClosedFile($id, 'prevention');
        }

        $recorded = $this->attempt(fn () => $this->commandBus->dispatch(new RecordPreventiveCare(
            clinicId: $animal->clinicId,
            animalId: $animal->id,
            kind: $request->request->getString('kind', 'vaccination'),
            label: $request->request->getString('label'),
            administeredOn: $request->request->getString('administered_on'),
            batchNumber: $this->trimmed($request, 'batch_number'),
            nextDueOn: $this->trimmed($request, 'next_due_on'),
            authorName: $this->staffNameResolver->resolve($animal->clinicId, $this->getUser()),
        )));

        if ($recorded) {
            $this->addFlash('success', 'Acte de prévention enregistré.');
        } else {
            $this->addFlash(
                'error',
                'L’acte n’a pas pu être enregistré : le protocole et la date sont obligatoires, '
                . 'la date ne peut pas être future et l’échéance doit lui être postérieure.',
            );
        }

        return $this->backToSheet($id, 'prevention');
    }
}
