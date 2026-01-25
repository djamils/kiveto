<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Animal;

use App\Animal\Application\Command\UpdateAnimalIdentity\UpdateAnimalIdentity;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route('/animals/{id}/update', name: 'clinic_animals_update', methods: ['POST'])]
final class UpdateAnimalController extends AbstractController
{
    private const string CSRF_ID = 'clinic_animal_form';

    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    public function __invoke(string $id, Request $request): Response
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

        // Update identity
        $this->commandBus->dispatch(new UpdateAnimalIdentity(
            clinicId: $currentClinicId->toString(),
            animalId: $id,
            name: $name,
            species: trim((string) $request->request->get('species', 'DOG')),
            sex: trim((string) $request->request->get('sex', 'UNKNOWN')),
            reproductiveStatus: trim((string) $request->request->get('reproductive_status', 'UNKNOWN')),
            isMixedBreed: (bool) $request->request->get('is_mixed_breed', false),
            breedName: $this->getOptionalString($request, 'breed_name'),
            birthDate: $this->getOptionalString($request, 'birth_date'),
            color: $this->getOptionalString($request, 'color'),
            photoUrl: $this->getOptionalString($request, 'photo_url'),
            microchipNumber: $this->getOptionalString($request, 'microchip_number'),
            tattooNumber: $this->getOptionalString($request, 'tattoo_number'),
            passportNumber: $this->getOptionalString($request, 'passport_number'),
            registryType: trim((string) $request->request->get('registry_type', 'NONE')),
            registryNumber: $this->getOptionalString($request, 'registry_number'),
            sireNumber: $this->getOptionalString($request, 'sire_number'),
            auxiliaryContactFirstName: $this->getOptionalString($request, 'auxiliary_contact_first_name'),
            auxiliaryContactLastName: $this->getOptionalString($request, 'auxiliary_contact_last_name'),
            auxiliaryContactPhoneNumber: $this->getOptionalString($request, 'auxiliary_contact_phone_number'),
        ));

        $this->addFlash('success', \sprintf('Animal "%s" modifié avec succès.', $name));

        return $this->redirectToRoute('clinic_animals_view', ['id' => $id]);
    }

    private function getOptionalString(Request $request, string $key): ?string
    {
        $value = trim((string) $request->request->get($key, ''));

        return '' !== $value ? $value : null;
    }
}
