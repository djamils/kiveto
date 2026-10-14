<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Animal\Sheet;

use App\Context\Animal\Application\Command\UpdateAnimalIdentity\UpdateAnimalIdentity;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The "Modifier" dialog of the animal sheet. It edits the fields the sheet
 * shows; everything else the animal carries is sent back unchanged.
 */
#[Route('/animals/{id}/profile', name: 'clinic_animals_profile_update', methods: ['POST'])]
final class UpdateAnimalProfileController extends AbstractAnimalSheetAction
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

        $name      = $this->trimmed($request, 'name');
        $microchip = $this->trimmed($request, 'microchip');

        if (null === $name) {
            $this->addFlash('error', 'Le nom de l’animal est obligatoire.');

            return $this->backToSheet($id);
        }

        $birthDate = $this->trimmed($request, 'birth_date');

        if (null !== $birthDate && !$this->isDate($birthDate)) {
            $this->addFlash('error', 'La date de naissance est invalide.');

            return $this->backToSheet($id);
        }

        // Only a number typed now is held to the ISO format: an older, shorter one stays as it is.
        $microchipChanged = $microchip !== $animal->identification->microchipNumber;

        if (null !== $microchip && $microchipChanged && 1 !== preg_match('/^\d{15}$/', $microchip)) {
            $this->addFlash('error', 'Le numéro de transpondeur comporte 15 chiffres.');

            return $this->backToSheet($id);
        }

        $saved = $this->attempt(fn () => $this->commandBus->dispatch(new UpdateAnimalIdentity(
            clinicId: $animal->clinicId,
            animalId: $animal->id,
            name: $name,
            species: $request->request->getString('species', $animal->species),
            sex: $request->request->getString('sex', $animal->sex),
            reproductiveStatus: $this->reproductiveStatus($request, $animal->reproductiveStatus),
            isMixedBreed: $animal->isMixedBreed,
            breedName: $this->trimmed($request, 'breed'),
            birthDate: $birthDate,
            color: $this->trimmed($request, 'color'),
            photoUrl: $animal->photoUrl,
            microchipNumber: $microchip,
            tattooNumber: $this->trimmed($request, 'tattoo'),
            passportNumber: $this->trimmed($request, 'passport'),
            registryType: $animal->identification->registryType,
            registryNumber: $animal->identification->registryNumber,
            registryReference: $animal->identification->registryReference,
            auxiliaryContactFirstName: $animal->auxiliaryContact?->firstName,
            auxiliaryContactLastName: $animal->auxiliaryContact?->lastName,
            auxiliaryContactPhoneNumber: $animal->auxiliaryContact?->phoneNumber,
        )));

        if ($saved) {
            $this->addFlash('success', 'Fiche de l’animal mise à jour.');
        } else {
            $this->addFlash(
                'error',
                'La fiche n’a pas pu être enregistrée : vérifiez la date de naissance et le transpondeur, '
                . 'qui ne doit pas déjà être attribué à un autre animal.',
            );
        }

        return $this->backToSheet($id);
    }

    /**
     * The dialog has a single switch. Left off on an animal whose status was
     * never established, it must not turn "unknown" into "intact".
     */
    private function reproductiveStatus(Request $request, string $current): string
    {
        if ($request->request->getBoolean('neutered')) {
            return 'neutered';
        }

        return 'unknown' === $current ? 'unknown' : 'intact';
    }
}
