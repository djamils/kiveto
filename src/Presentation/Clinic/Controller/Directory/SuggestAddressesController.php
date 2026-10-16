<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Directory;

use App\Context\Client\Application\Port\AddressSuggestion;
use App\Context\Client\Application\Query\SuggestPostalAddresses\SuggestPostalAddresses;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\ClinicDto;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\GetClinic;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * « Tapez le début : les adresses connues sont proposées » — the addresses
 * of the Base Adresse Nationale the start typed (`q`) may be, those near the
 * clinic first, as JSON.
 */
#[Route('/directory/addresses', name: 'clinic_directory_addresses', methods: ['GET'])]
final class SuggestAddressesController extends AbstractController
{
    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $clinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $clinicId);

        $clinic = $this->queryBus->ask(new GetClinic($clinicId->toString()));
        $near   = null;
        foreach ($clinic instanceof ClinicDto ? $clinic->letterhead->addressLines ?? [] : [] as $line) {
            if (null === $near && 1 === preg_match('/\b(\d{5})\b/', $line, $m)) {
                $near = $m[1];
            }
        }

        $found = $this->queryBus->ask(new SuggestPostalAddresses(mb_substr($request->query->getString('q'), 0, 120), 6, $near));

        return new JsonResponse(['items' => array_values(array_map(
            static fn (AddressSuggestion $address): array => ['rue' => $address->street, 'cp' => $address->postalCode, 'ville' => $address->city],
            array_filter(\is_array($found) ? $found : [], static fn (mixed $address): bool => $address instanceof AddressSuggestion),
        ))]);
    }
}
