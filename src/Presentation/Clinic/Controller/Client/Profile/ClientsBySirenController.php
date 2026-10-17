<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Client\Profile;

use App\Context\Client\Application\Query\ListClientIdsBySiren\ListClientIdsBySiren;
use App\Presentation\Clinic\ViewModel\Directory\ClientLookupReader;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * « Un client porte déjà ce SIREN »: the clients of the clinic, archived ones
 * included, carrying the SIREN typed (`siren`), as JSON. Several
 * establishments share a SIREN: the window only warns.
 */
#[Route('/clients/by-siren', name: 'clinic_clients_by_siren', methods: ['GET'])]
final class ClientsBySirenController extends AbstractController
{
    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly ClientLookupReader $clients,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $clinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $clinicId);

        $ids = $this->queryBus->ask(new ListClientIdsBySiren($clinicId->toString(), $request->query->getString('siren')));

        return new JsonResponse(['items' => $this->clients->byIds(
            $clinicId->toString(),
            array_values(array_filter(\is_array($ids) ? $ids : [], \is_string(...))),
        )]);
    }
}
