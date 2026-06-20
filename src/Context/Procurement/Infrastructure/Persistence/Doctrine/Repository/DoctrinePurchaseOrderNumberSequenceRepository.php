<?php

declare(strict_types=1);

namespace App\Context\Procurement\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Procurement\Domain\PurchaseOrder\Service\PurchaseOrderNumberGeneratorInterface;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderNumber;
use App\Context\Procurement\Domain\Shared\ValueObject\ClinicId;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrinePurchaseOrderNumberSequenceRepository implements PurchaseOrderNumberGeneratorInterface
{
    public function __construct(private Connection $connection)
    {
    }

    public function next(ClinicId $clinicId, int $year): PurchaseOrderNumber
    {
        $clinicIdBin = Uuid::fromString($clinicId->toString())->toBinary();

        // SELECT FOR UPDATE to prevent concurrent duplicates
        $row = $this->connection->fetchAssociative(
            'SELECT last_number FROM procurement__purchase_order_number_sequences WHERE clinic_id = :clinicId AND year = :year FOR UPDATE',
            ['clinicId' => $clinicIdBin, 'year' => $year],
        );

        if (false === $row) {
            // Insert first row for this clinic+year
            $this->connection->executeStatement(
                'INSERT INTO procurement__purchase_order_number_sequences (clinic_id, year, last_number) VALUES (:clinicId, :year, 1)',
                ['clinicId' => $clinicIdBin, 'year' => $year],
            );
            $nextNumber = 1;
        } else {
            $nextNumber = RowAccessor::int($row, 'last_number') + 1;
            $this->connection->executeStatement(
                'UPDATE procurement__purchase_order_number_sequences SET last_number = :nextNumber WHERE clinic_id = :clinicId AND year = :year',
                ['nextNumber' => $nextNumber, 'clinicId' => $clinicIdBin, 'year' => $year],
            );
        }

        return PurchaseOrderNumber::fromString(\sprintf('PO-%d-%06d', $year, $nextNumber));
    }
}
