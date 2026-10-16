<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Directory;

use App\Presentation\Clinic\ViewModel\Directory\ClientLookupReader;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The active clients a name, a phone or an animal finds, as JSON: the owner
 * of the « Nouvel animal » window, the homonyms of the « Nouveau client » one.
 */
#[Route('/directory/clients', name: 'clinic_directory_clients', methods: ['GET'])]
final class LookupDirectoryClientsController extends AbstractController
{
    private const int LIMIT = 6;

    public function __construct(
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly ClientLookupReader $clients,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $clinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $clinicId);

        return new JsonResponse(['items' => $this->clients->search($clinicId->toString(), $request->query->getString('q'), self::LIMIT)]);
    }
}
