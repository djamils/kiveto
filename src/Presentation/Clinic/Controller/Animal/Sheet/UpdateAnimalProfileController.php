<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Animal\Sheet;

use App\Context\Animal\Application\Command\UpdateAnimalIdentity\UpdateAnimalIdentity;
use App\Context\Animal\Application\Command\UpdateAnimalInsurance\UpdateAnimalInsurance;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The « Identité » window of the animal sheet: name, species, breed, coat,
 * sex and sterilisation, birth date, microchip and health insurance.
 * Everything else the animal carries is sent back unchanged.
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
        $insurer   = $this->trimmed($request, 'insurer');
        $policy    = $this->trimmed($request, 'policy_number');

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
            $this->addFlash('error', 'Le numéro de puce comporte 15 chiffres.');

            return $this->backToSheet($id);
        }

        if (null !== $insurer && null === $policy) {
            $this->addFlash('error', 'Indiquez le numéro de contrat de l’assurance santé.');

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
            tattooNumber: $animal->identification->tattooNumber,
            passportNumber: $animal->identification->passportNumber,
            registryType: $animal->identification->registryType,
            registryNumber: $animal->identification->registryNumber,
            registryReference: $animal->identification->registryReference,
            auxiliaryContactFirstName: $animal->auxiliaryContact?->firstName,
            auxiliaryContactLastName: $animal->auxiliaryContact?->lastName,
            auxiliaryContactPhoneNumber: $animal->auxiliaryContact?->phoneNumber,
        )));

        $insured = $saved && $this->attempt(fn () => $this->commandBus->dispatch(new UpdateAnimalInsurance(
            clinicId: $animal->clinicId,
            animalId: $animal->id,
            insurerName: $insurer,
            policyNumber: $policy,
        )));

        if ($insured) {
            $this->addFlash('success', 'Identité de l’animal mise à jour.');
        } elseif ($saved) {
            $this->addFlash('error', 'L’identité est enregistrée, mais pas l’assurance : le numéro de contrat compte de 4 à 20 lettres ou chiffres.');
        } else {
            $this->addFlash(
                'error',
                'L’identité n’a pas pu être enregistrée : vérifiez la date de naissance et la puce, '
                . 'qui ne doit pas déjà être attribuée à un autre animal.',
            );
        }

        return $this->backToSheet($id, $saved ? 'head' : null);
    }

    /**
     * The window has a single switch. Left off on an animal whose status was
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
