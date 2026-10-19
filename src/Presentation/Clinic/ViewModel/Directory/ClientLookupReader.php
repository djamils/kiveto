<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Directory;

use App\Context\Animal\Application\Query\ListAnimalSummariesPerClientIds\AnimalSummary;
use App\Context\Animal\Application\Query\ListAnimalSummariesPerClientIds\ListAnimalSummariesPerClientIds;
use App\Context\Animal\Application\Query\ListDirectoryMatches\DirectoryMatches;
use App\Context\Animal\Application\Query\ListDirectoryMatches\ListDirectoryMatches;
use App\Context\Client\Application\Query\GetClientById\ClientView;
use App\Context\Client\Application\Query\GetClientById\GetClientById;
use App\Context\Client\Application\Query\SearchClients\ClientListItemView;
use App\Context\Client\Application\Query\SearchClients\SearchClients;
use App\Context\Client\Domain\TaxProfile\TaxProfileWarning;
use App\Shared\Application\Bus\QueryBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The client cards the pickers show while a client is looked up — the
 * « Vente au comptoir » window, the owner of a new animal, a homonym or a
 * phone already on file in the « Nouveau client » window: the name with its
 * civility, the main phone, the city, the address and the first animals —
 * and for a structure its legal name, SIREN, VAT number and badge (« Pro »
 * for a VAT-liable business, « Asso » for a structure that is not) and the
 * verification warning worth repeating at the counter (`warn`, never blocking).
 *
 * @phpstan-type ClientCard array{id: string, n: string, ini: string, tel: string|null, ville: string|null, adr: string|null, ani: list<array{e: string, nom: string}>, url: string, rs: string|null, siren: string|null, tva: string|null, fisc: string, badge: string|null, warn: string|null}
 */
final readonly class ClientLookupReader
{
    private const array EMOJI = ['dog' => '🐕', 'cat' => '🐈', 'nac' => '🐇'];

    public function __construct(
        private QueryBusInterface $queryBus,
        private UrlGeneratorInterface $urls,
    ) {
    }

    /**
     * The active clients a name, a phone (two letters or three digits at
     * least) or one of their animals' names finds. For structures only, the
     * legal name or the SIREN finds them; the animals' index is not asked
     * (it would bring back people).
     *
     * @return list<ClientCard>
     */
    public function search(string $clinicId, string $term, int $limit, bool $structuresOnly = false): array
    {
        $term   = mb_substr(mb_trim($term), 0, 100);
        $digits = (string) preg_replace('/\D+/', '', $term);

        if (mb_strlen($term) < 2 && \strlen($digits) < 3) {
            return [];
        }

        // « 06 12 34 » is looked up as the digits the phones are stored with.
        $search = \strlen($digits) >= 3 && '' === mb_trim((string) preg_replace('/[\d\s.]+/', '', $term)) ? $digits : $term;
        if ($structuresOnly) {
            return $this->cards($clinicId, $this->queryBus->ask(new SearchClients(
                clinicId: $clinicId,
                searchTerm: $term,
                status: 'active',
                limit: $limit,
                withLegalNameOnly: true,
            )));
        }

        $matches = $this->queryBus->ask(new ListDirectoryMatches($clinicId, $term));

        return $this->cards($clinicId, $this->queryBus->ask(new SearchClients(
            clinicId: $clinicId,
            searchTerm: $search,
            status: 'active',
            limit: $limit,
            termMatchingIds: $matches instanceof DirectoryMatches ? $matches->ownerIds() : [],
        )));
    }

    /**
     * These clients, archived ones included, in the order given.
     *
     * @param list<string> $clientIds
     *
     * @return list<ClientCard>
     */
    public function byIds(string $clinicId, array $clientIds): array
    {
        if ([] === $clientIds) {
            return [];
        }

        $cards = $this->cards($clinicId, $this->queryBus->ask(new SearchClients(
            clinicId: $clinicId,
            limit: \count($clientIds),
            restrictToIds: $clientIds,
        )));
        $rank = array_flip($clientIds);
        usort($cards, static fn (array $a, array $b): int => ($rank[$a['id']] ?? 0) <=> ($rank[$b['id']] ?? 0));

        return $cards;
    }

    /** « +33612345678 » reads « 06 12 34 56 78 »; a foreign number stays as stored. */
    public static function phone(?string $phone): ?string
    {
        if (null === $phone || 1 !== preg_match('/^\+33(\d{9})$/', $phone, $m)) {
            return $phone;
        }

        return mb_trim(chunk_split('0' . $m[1], 2, ' '));
    }

    /**
     * The first verification warning that matters when billing (ceased, unknown SIREN, VAT rejected).
     */
    private static function counterWarning(ClientView $card): ?string
    {
        foreach ($card->taxWarnings as $code) {
            $warning = TaxProfileWarning::tryFrom($code);

            if (null !== $warning && $warning->shownAtCounter()) {
                return $warning->label();
            }
        }

        return null;
    }

    private static function badge(?ClientView $card, ?string $legalName): ?string
    {
        if (null !== $card && 'french_business' === $card->taxStatus) {
            return 'Pro';
        }

        return null !== $legalName ? 'Asso' : null;
    }

    /**
     * @return list<ClientCard>
     */
    private function cards(string $clinicId, mixed $result): array
    {
        $clients = array_values(array_filter(
            \is_array($result) && \is_array($result['items'] ?? null) ? $result['items'] : [],
            static fn (mixed $client): bool => $client instanceof ClientListItemView,
        ));

        if ([] === $clients) {
            return [];
        }

        $animals = $this->queryBus->ask(new ListAnimalSummariesPerClientIds(
            $clinicId,
            array_map(static fn (ClientListItemView $client): string => $client->id, $clients),
            3,
        ));

        $cards = [];
        foreach ($clients as $client) {
            // The card shows the civility and the postal address, which the list does not carry.
            $card    = $this->queryBus->ask(new GetClientById($clinicId, $client->id));
            $title   = $card instanceof ClientView ? ['madam' => 'Mme ', 'mister' => 'M. '][$card->civility] ?? '' : '';
            $address = $card instanceof ClientView ? $card->postalAddress : null;
            $owned   = \is_array($animals) && \is_array($animals[$client->id] ?? null) ? $animals[$client->id] : [];

            $cards[] = [
                'id'    => $client->id,
                'n'     => $title . $client->fullName(),
                'ini'   => mb_strtoupper(mb_substr($client->firstName, 0, 1) . mb_substr($client->lastName, 0, 1)),
                'tel'   => self::phone($client->primaryPhone),
                'ville' => $address->city ?? $client->city,
                'adr'   => null !== $address
                    ? mb_trim($address->streetLine1 . ', ' . mb_trim(($address->postalCode ?? '') . ' ' . $address->city), ', ')
                    : $client->city,
                'ani' => array_values(array_map(
                    static fn (AnimalSummary $animal): array => ['e' => self::EMOJI[$animal->species] ?? '🐾', 'nom' => $animal->name],
                    array_filter($owned, static fn (mixed $animal): bool => $animal instanceof AnimalSummary),
                )),
                'url'   => $this->urls->generate('clinic_clients_view', ['id' => $client->id]),
                'rs'    => $client->legalName,
                'siren' => $card instanceof ClientView ? $card->siren : null,
                'tva'   => $card instanceof ClientView ? $card->vatNumber : null,
                'fisc'  => $card instanceof ClientView && 'french_business' === $card->taxStatus ? 'pro' : 'ind',
                'badge' => self::badge($card instanceof ClientView ? $card : null, $client->legalName),
                'warn'  => $card instanceof ClientView ? self::counterWarning($card) : null,
            ];
        }

        return $cards;
    }
}
