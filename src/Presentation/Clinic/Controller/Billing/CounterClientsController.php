<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Billing;

use App\Context\Animal\Application\Query\ListDirectoryMatches\DirectoryMatches;
use App\Context\Animal\Application\Query\ListDirectoryMatches\ListDirectoryMatches;
use App\Context\Client\Application\Query\GetClientById\ClientView;
use App\Context\Client\Application\Query\GetClientById\GetClientById;
use App\Context\Client\Application\Query\SearchClients\ClientListItemView;
use App\Context\Client\Application\Query\SearchClients\SearchClients;
use App\Shared\Application\Bus\QueryBusInterface;
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
        private readonly QueryBusInterface $queryBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        $clinicId = $currentClinicId->toString();
        $term     = mb_substr(mb_trim($request->query->getString('q')), 0, 100);
        $digits   = (string) preg_replace('/\D+/', '', $term);

        if (mb_strlen($term) < 2 && \strlen($digits) < 3) {
            return new JsonResponse(['items' => []]);
        }

        // « 06 12 34 » is looked up as the digits the phones are stored with.
        $search  = \strlen($digits) >= 3 && '' === mb_trim((string) preg_replace('/[\d\s.]+/', '', $term)) ? $digits : $term;
        $matches = $this->queryBus->ask(new ListDirectoryMatches($clinicId, $term));

        $result = $this->queryBus->ask(new SearchClients(
            clinicId: $clinicId,
            searchTerm: $search,
            status: 'active',
            limit: self::LIMIT,
            termMatchingIds: $matches instanceof DirectoryMatches ? $matches->ownerIds() : [],
        ));

        $items = [];

        foreach (\is_array($result) && \is_array($result['items'] ?? null) ? $result['items'] : [] as $client) {
            if ($client instanceof ClientListItemView) {
                // The card shows the civility and the postal address, which the list does not carry.
                $card  = $this->queryBus->ask(new GetClientById($clinicId, $client->id));
                $title = $card instanceof ClientView ? match ($card->civility) {
                    'madam'  => 'Mme ',
                    'mister' => 'M. ',
                    default  => '',
                } : '';
                $address = $card instanceof ClientView ? $card->postalAddress : null;

                $items[] = [
                    'id'  => $client->id,
                    'n'   => $title . $client->fullName(),
                    'tel' => self::phone($client->primaryPhone),
                    'adr' => null !== $address
                        ? mb_trim($address->streetLine1 . ', ' . mb_trim(($address->postalCode ?? '') . ' ' . $address->city), ', ')
                        : $client->city,
                ];
            }
        }

        return new JsonResponse(['items' => $items]);
    }

    /** « +33612345678 » reads « 06 12 34 56 78 »; a foreign number stays as stored. */
    private static function phone(?string $phone): ?string
    {
        if (null === $phone || 1 !== preg_match('/^\+33(\d{9})$/', $phone, $m)) {
            return $phone;
        }

        return mb_trim(chunk_split('0' . $m[1], 2, ' '));
    }
}
