<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Port;

use App\Context\Client\Application\Query\GetClientConsents\ClientConsentsView;
use App\Context\Client\Application\Query\ListClientConsentHistory\ConsentHistoryItemView;
use App\Context\Client\Domain\ValueObject\ClinicId;

/**
 * Read side of the consents and of their history.
 */
interface ClientConsentReaderInterface
{
    /**
     * @param non-empty-list<string> $clientIds
     *
     * @return array<string, ClientConsentsView> keyed by client id, the clients of the clinic only
     */
    public function consentsOf(ClinicId $clinicId, array $clientIds): array;

    /**
     * History rows of the clients, newest first (`at` then `id`, descending),
     * strictly after the given position when there is one.
     *
     * @param non-empty-list<string>                         $clientIds
     * @param array{at: \DateTimeImmutable, id: string}|null $after
     *
     * @return list<ConsentHistoryItemView>
     */
    public function historyOf(ClinicId $clinicId, array $clientIds, ?array $after, int $limit): array;
}
