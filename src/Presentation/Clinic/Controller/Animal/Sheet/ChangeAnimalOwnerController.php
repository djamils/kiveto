<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Animal\Sheet;

use App\Context\Animal\Application\Command\ReplaceAnimalOwners\ReplaceAnimalOwners;
use App\Context\Client\Application\Query\GetClientById\ClientView;
use App\Context\Client\Application\Query\GetClientById\GetClientById;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/animals/{id}/owner', name: 'clinic_animals_owner_change', methods: ['POST'])]
final class ChangeAnimalOwnerController extends AbstractAnimalSheetAction
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly QueryBusInterface $queryBus,
    ) {
    }

    public function __invoke(string $id, Request $request): Response
    {
        $this->guard($request);

        $animal = $this->animal($id);

        if (!$this->isOpen($animal)) {
            return $this->refuseClosedFile($id);
        }

        $newOwnerId = $this->trimmed($request, 'owner_id');

        if (null === $newOwnerId || 1 !== preg_match('/^[0-9a-f]{8}(-[0-9a-f]{4}){3}-[0-9a-f]{12}$/i', $newOwnerId)) {
            $this->addFlash('error', 'Sélectionnez le nouveau propriétaire dans la liste.');

            return $this->backToSheet($id);
        }

        if ($newOwnerId === $animal->primaryOwnerClientId()) {
            $this->addFlash('error', 'Ce client est déjà le propriétaire de l’animal.');

            return $this->backToSheet($id);
        }

        // The id comes from the form: the new owner must be an active client of this clinic.
        $owner = $this->queryBus->ask(new GetClientById($animal->clinicId, $newOwnerId));

        if (!$owner instanceof ClientView || 'active' !== $owner->status) {
            $this->addFlash('error', 'Ce client est introuvable ou archivé.');

            return $this->backToSheet($id);
        }

        $changed = $this->attempt(fn () => $this->commandBus->dispatch(new ReplaceAnimalOwners(
            clinicId: $animal->clinicId,
            animalId: $animal->id,
            primaryOwnerClientId: $newOwnerId,
            // A co-owner who becomes the owner stops being a co-owner; the others stay.
            secondaryOwnerClientIds: array_values(array_filter(
                $animal->secondaryOwnerClientIds(),
                static fn (string $clientId): bool => $clientId !== $newOwnerId,
            )),
        )));

        if ($changed) {
            $this->addFlash('success', \sprintf('%s est désormais rattaché à %s.', $animal->name, $owner->fullName()));
        } else {
            $this->addFlash('error', 'Le changement de propriétaire n’a pas pu être enregistré.');
        }

        return $this->backToSheet($id);
    }
}
