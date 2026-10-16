<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Client\Profile;

use App\Presentation\Clinic\ViewModel\Directory\DirectoryRegistrar;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * The directory's « Nouveau client » window: the client and their animals in
 * one go (`payload`, the window's state as JSON). The directory comes back
 * on the new client, with a toast; the window had already checked what it
 * sent, so a refusal here is a race (a phone or a chip recorded meanwhile).
 */
#[Route('/clients/create', name: 'clinic_clients_create', methods: ['POST'])]
final class CreateClientController extends AbstractController
{
    public function __construct(
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly DirectoryRegistrar $registrar,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('directory_create', $request->request->getString('_token')))) {
            $this->addFlash('error', 'Token de sécurité invalide. Veuillez réessayer.');

            return $this->redirectToRoute('clinic_clients_list', [], Response::HTTP_SEE_OTHER);
        }

        $clinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $clinicId);

        $payload = json_decode($request->request->getString('payload'), true);
        $result  = $this->registrar->createClient($clinicId->toString(), \is_array($payload) ? $payload : []);

        $this->addFlash($result['ok'] ? 'success' : 'error', $result['message']);

        return $this->redirectToRoute(
            'clinic_clients_list',
            $result['ok'] ? ['mode' => 'clients', 'q' => $result['search']] : ['mode' => 'clients'],
            Response::HTTP_SEE_OTHER,
        );
    }
}
