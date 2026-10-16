<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Animal\Profile;

use App\Presentation\Clinic\ViewModel\Directory\DirectoryRegistrar;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * The directory's « Nouvel animal » window: an animal for a client on file
 * (`payload`, the window's state as JSON). The directory comes back on the
 * animals' view, on the new animal, with a toast.
 */
#[Route('/animals/create', name: 'clinic_animals_create', methods: ['POST'])]
final class CreateAnimalController extends AbstractController
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

            return $this->redirectToRoute('clinic_clients_list', ['mode' => 'animals'], Response::HTTP_SEE_OTHER);
        }

        $clinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $clinicId);

        $payload = json_decode($request->request->getString('payload'), true);
        $result  = $this->registrar->createAnimal($clinicId->toString(), \is_array($payload) ? $payload : []);

        $this->addFlash($result['ok'] ? 'success' : 'error', $result['message']);

        return $this->redirectToRoute(
            'clinic_clients_list',
            $result['ok'] ? ['mode' => 'animals', 'q' => $result['search']] : ['mode' => 'animals'],
            Response::HTTP_SEE_OTHER,
        );
    }
}
