<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Billing;

use App\Presentation\Clinic\ViewModel\Directory\ClientLookupReader;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The clients of the clinic the « Vente au comptoir » window finds by name,
 * phone or animal, as JSON. A name needs two letters, a phone three digits.
 */
#[Route(path: '/billing/counter-clients', name: 'clinic_counter_clients', methods: ['GET'])]
final class CounterClientsController extends AbstractController
{
    private const int LIMIT = 6;

    public function __construct(
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly ClientLookupReader $clients,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        return new JsonResponse(['items' => $this->clients->search(
            $currentClinicId->toString(),
            $request->query->getString('q'),
            self::LIMIT,
        )]);
    }
}
