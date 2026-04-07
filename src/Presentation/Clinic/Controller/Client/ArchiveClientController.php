<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Client;

use App\Client\Application\Command\ArchiveClient\ArchiveClient;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route('/clients/{id}/archive', name: 'clinic_clients_archive', methods: ['POST'])]
final class ArchiveClientController extends AbstractController
{
    private const string CSRF_ID = 'clinic_client_form';

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

        $this->commandBus->dispatch(new ArchiveClient(
            clinicId: $currentClinicId->toString(),
            clientId: $id,
        ));

        $this->addFlash('success', 'Client archivé avec succès.');

        return $this->redirectToRoute('clinic_clients_list');
    }
}
