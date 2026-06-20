<?php

declare(strict_types=1);

namespace App\Context\Procurement\Infrastructure\Adapter\Clinic;

use App\Context\Procurement\Application\Port\ClinicProviderInterface;
use App\Context\Procurement\Domain\Shared\ValueObject\ClinicId;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

/**
 * Queries clinic__clinics table directly via DBAL for currency code.
 * Does NOT use EntityManager or any Clinic BC domain classes.
 */
final readonly class ClinicProviderAdapter implements ClinicProviderInterface
{
    public function __construct(private Connection $connection)
    {
    }

    public function getCurrency(ClinicId $clinicId): string
    {
        $row = $this->connection->fetchAssociative(
            'SELECT currency_code FROM clinic__clinics WHERE id = :id LIMIT 1',
            ['id' => Uuid::fromString($clinicId->toString())->toBinary()],
        );

        if (false === $row || !isset($row['currency_code'])) {
            return 'EUR';
        }

        $code = $row['currency_code'];
        if (!\is_string($code) || '' === $code) {
            return 'EUR';
        }

        return $code;
    }
}
