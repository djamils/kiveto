<?php

declare(strict_types=1);

namespace App\System\Notification\Infrastructure\Persistence\Doctrine\Repository;

use App\System\Notification\Domain\Quota\SmsThreshold;
use App\System\Notification\Domain\Quota\SmsThresholdRepositoryInterface;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

/**
 * INSERT IGNORE on the (clinic, month, threshold) primary key: the first
 * writer wins, any later or concurrent one inserts nothing.
 */
final readonly class DbalSmsThresholdRepository implements SmsThresholdRepositoryInterface
{
    public function __construct(private Connection $connection)
    {
    }

    public function markCrossed(string $clinicId, string $month, SmsThreshold $threshold, \DateTimeImmutable $at): bool
    {
        return 1 === (int) $this->connection->executeStatement(
            'INSERT IGNORE INTO notification__sms_thresholds_crossed (clinic_id, month, threshold, crossed_at) VALUES (:clinic, :month, :threshold, :at)',
            [
                'clinic'    => Uuid::fromString($clinicId)->toBinary(),
                'month'     => $month,
                'threshold' => $threshold->value,
                'at'        => $at->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            ],
        );
    }
}
