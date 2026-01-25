<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Animal;

use App\Animal\Application\Command\ArchiveAnimal\ArchiveAnimal;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route('/animals/{id}/archive', name: 'clinic_animals_archive', methods: ['POST'])]
final class ArchiveAnimalController extends AbstractController
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

        $this->commandBus->dispatch(new ArchiveAnimal(
            clinicId: $currentClinicId->toString(),
            animalId: $id,
        ));

        $this->addFlash('success', 'Animal archivé avec succès.');

        return $this->redirectToRoute('clinic_animals_list');
    }
}
