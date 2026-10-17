<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Client\Profile;

use App\Context\Client\Application\Query\ListClientIdsBySiren\ListClientIdsBySiren;
use App\Presentation\Clinic\ViewModel\Client\TaxProfileInput;
use App\Presentation\Clinic\ViewModel\Directory\ClientLookupReader;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The live check of the tax-profile block's SIREN (`siren`), as JSON: the
 * SIREN it stands for (a pasted SIRET reduced) or what to correct, and
 * « Un client porte déjà ce SIREN »: the clients of the clinic, archived ones
 * included, carrying it. Several establishments share a SIREN: the window
 * only warns.
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

        $check = TaxProfileInput::checkSiren($request->query->getString('siren'));
        $ids   = null === $check['siren'] ? [] : $this->queryBus->ask(new ListClientIdsBySiren($clinicId->toString(), $check['siren']));

        return new JsonResponse([...$check, 'items' => $this->clients->byIds(
            $clinicId->toString(),
            array_values(array_filter(\is_array($ids) ? $ids : [], \is_string(...))),
        )]);
    }
}
