<?php

declare(strict_types=1);

namespace App\Context\Client\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Client\Domain\Repository\ConsentHistoryRepositoryInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Symfony\Component\Uid\Uuid;

/**
 * Appends the history rows in SQL, in the caller's transaction. Dates are
 * stored in UTC; ids are UUID v7, so rows of one change keep their order.
 */
final readonly class DbalConsentHistoryRepository implements ConsentHistoryRepositoryInterface
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function append(array $entries): void
    {
        foreach ($entries as $entry) {
            $this->connection->insert('client__consent_history', [
                'id'              => Uuid::v7()->toBinary(),
                'clinic_id'       => Uuid::fromString($entry->clinicId->toString())->toBinary(),
                'client_id'       => Uuid::fromString($entry->clientId->toString())->toBinary(),
                'kind'            => $entry->change->kind->value,
                'channel'         => $entry->change->channel->value,
                'action'          => $entry->change->action->value,
                'at'              => $entry->at->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
                'source'          => $entry->source->value,
                'actor_type'      => $entry->actor->type->value,
                'actor_id'        => null === $entry->actor->id ? null : Uuid::fromString($entry->actor->id)->toBinary(),
                'author_label'    => $entry->actor->label,
                'wording_version' => $entry->wordingVersion,
                'approximate'     => $entry->approximate ? 1 : 0,
            ], [
                'id'        => ParameterType::BINARY,
                'clinic_id' => ParameterType::BINARY,
                'client_id' => ParameterType::BINARY,
                'actor_id'  => ParameterType::BINARY,
            ]);
        }
    }
}
