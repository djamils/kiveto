<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Animal;

use App\Animal\Application\Command\CreateAnimal\CreateAnimal;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route('/animals/create', name: 'clinic_animals_create', methods: ['POST'])]
final class CreateAnimalController extends AbstractController
{
    private const string CSRF_ID = 'clinic_animal_form';

    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $token = new CsrfToken(self::CSRF_ID, (string) $request->request->get('_token'));

        if (!$this->csrfTokenManager->isTokenValid($token)) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        $name = trim((string) $request->request->get('name'));

        if ('' === $name) {
            throw new \InvalidArgumentException('Le nom est obligatoire.');
        }

        $primaryOwnerClientId = trim((string) $request->request->get('primary_owner_client_id'));

        if ('' === $primaryOwnerClientId) {
            throw new \InvalidArgumentException('Un propriétaire principal est obligatoire.');
        }

        $animalId = $this->commandBus->dispatch(new CreateAnimal(
            clinicId: $currentClinicId->toString(),
            name: $name,
            species: trim((string) $request->request->get('species', 'dog')),
            sex: trim((string) $request->request->get('sex', 'unknown')),
            reproductiveStatus: trim((string) $request->request->get('reproductive_status', 'unknown')),
            isMixedBreed: (bool) $request->request->get('is_mixed_breed', false),
            breedName: $this->getOptionalString($request, 'breed_name'),
            birthDate: $this->getOptionalString($request, 'birth_date'),
            color: $this->getOptionalString($request, 'color'),
            photoUrl: $this->getOptionalString($request, 'photo_url'),
            microchipNumber: $this->getOptionalString($request, 'microchip_number'),
            tattooNumber: $this->getOptionalString($request, 'tattoo_number'),
            passportNumber: $this->getOptionalString($request, 'passport_number'),
            registryType: trim((string) $request->request->get('registry_type', 'none')),
            registryNumber: $this->getOptionalString($request, 'registry_number'),
            sireNumber: $this->getOptionalString($request, 'sire_number'),
            lifeStatus: trim((string) $request->request->get('life_status', 'alive')),
            deceasedAt: $this->getOptionalString($request, 'deceased_at'),
            missingSince: $this->getOptionalString($request, 'missing_since'),
            transferStatus: trim((string) $request->request->get('transfer_status', 'none')),
            soldAt: $this->getOptionalString($request, 'sold_at'),
            givenAt: $this->getOptionalString($request, 'given_at'),
            auxiliaryContactFirstName: $this->getOptionalString($request, 'auxiliary_contact_first_name'),
            auxiliaryContactLastName: $this->getOptionalString($request, 'auxiliary_contact_last_name'),
            auxiliaryContactPhoneNumber: $this->getOptionalString($request, 'auxiliary_contact_phone_number'),
            primaryOwnerClientId: $primaryOwnerClientId,
            secondaryOwnerClientIds: [],
        ));

        $this->addFlash('success', \sprintf('Animal "%s" créé avec succès.', $name));

        return $this->redirectToRoute('clinic_animals_view', ['id' => $animalId]);
    }

    private function getOptionalString(Request $request, string $key): ?string
    {
        $value = trim((string) $request->request->get($key, ''));

        return '' !== $value ? $value : null;
    }
}
