<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Directory;

use App\Context\Client\Application\Query\ListClientIdsByPhone\ListClientIdsByPhone;
use App\Presentation\Clinic\ViewModel\Directory\ClientLookupReader;
use App\Presentation\Clinic\ViewModel\Flux\DeskRegistration;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * « Ce numéro est déjà celui de… »: the clients, archived ones included, one
 * of whose phones is the ten-digit number typed (`tel`), as JSON.
 */
#[Route('/directory/phone-owners', name: 'clinic_directory_phone_owners', methods: ['GET'])]
final class LookupPhoneOwnersController extends AbstractController
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

        $digits = (string) preg_replace('/\D+/', '', $request->query->getString('tel'));

        if (1 !== preg_match('/^0[1-9]\d{8}$/', $digits)) {
            return new JsonResponse(['items' => []]);
        }

        $ids = $this->queryBus->ask(new ListClientIdsByPhone($clinicId->toString(), DeskRegistration::e164($digits)));

        return new JsonResponse(['items' => $this->clients->byIds(
            $clinicId->toString(),
            array_values(array_filter(\is_array($ids) ? $ids : [], \is_string(...))),
        )]);
    }
}
