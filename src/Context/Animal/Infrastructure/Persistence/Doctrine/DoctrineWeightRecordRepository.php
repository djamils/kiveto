<?php

declare(strict_types=1);

namespace App\Context\Animal\Infrastructure\Persistence\Doctrine;

use App\Context\Animal\Domain\Repository\WeightRecordRepositoryInterface;
use App\Context\Animal\Domain\WeightRecord;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineWeightRecordRepository implements WeightRecordRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private WeightRecordMapper $mapper,
    ) {
    }

    public function save(WeightRecord $record): void
    {
        // Records are append-only: there is nothing to update on an existing row.
        $this->em->persist($this->mapper->toEntity($record));
        $this->em->flush();
    }
}
