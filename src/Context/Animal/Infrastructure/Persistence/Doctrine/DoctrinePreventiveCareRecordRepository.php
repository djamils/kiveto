<?php

declare(strict_types=1);

namespace App\Context\Animal\Infrastructure\Persistence\Doctrine;

use App\Context\Animal\Domain\PreventiveCareRecord;
use App\Context\Animal\Domain\Repository\PreventiveCareRecordRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrinePreventiveCareRecordRepository implements PreventiveCareRecordRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private PreventiveCareRecordMapper $mapper,
    ) {
    }

    public function save(PreventiveCareRecord $record): void
    {
        // Records are append-only: there is nothing to update on an existing row.
        $this->em->persist($this->mapper->toEntity($record));
        $this->em->flush();
    }
}
