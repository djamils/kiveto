<?php

declare(strict_types=1);

namespace App\System\Audit\Infrastructure\Persistence\Doctrine\Repository;

use App\System\Audit\Domain\AuditEntry;
use App\System\Audit\Domain\Repository\AuditEntryRepositoryInterface;
use App\System\Audit\Domain\ValueObject\AuditDetail;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

/**
 * A plain INSERT: the log has no update path. Dates are written in UTC.
 */
final readonly class DbalAuditEntryRepository implements AuditEntryRepositoryInterface
{
    public const string TABLE = 'audit__entries';

    public function __construct(private Connection $connection)
    {
    }

    public function append(AuditEntry $entry): void
    {
        $actor = $entry->actor();

        $this->connection->insert(self::TABLE, [
            'id'             => Uuid::fromString($entry->id()->toString())->toBinary(),
            'occurred_at'    => $entry->occurredAt()->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            'actor_kind'     => $actor->kind()->value,
            'actor_id'       => self::nullableBinary($actor->id()),
            'actor_name'     => $actor->name(),
            'action'         => $entry->action()->toString(),
            'category'       => $entry->category()->value,
            'summary'        => $entry->summary(),
            'clinic_id'      => self::nullableBinary($entry->clinicId()),
            'clinic_name'    => $entry->clinicName(),
            'target_user_id' => self::nullableBinary($entry->targetUserId()),
            'ip_address'     => $entry->ipAddress(),
            'details'        => json_encode(
                array_map(
                    static fn (AuditDetail $detail): array => ['label' => $detail->label(), 'value' => $detail->value()],
                    $entry->details(),
                ),
                \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE,
            ),
        ]);
    }

    private static function nullableBinary(?string $uuid): ?string
    {
        return null !== $uuid ? Uuid::fromString($uuid)->toBinary() : null;
    }
}
