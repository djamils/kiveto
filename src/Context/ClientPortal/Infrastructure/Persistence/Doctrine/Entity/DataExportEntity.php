<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Schema of the owners' data exports; rows are written and read in SQL by
 * DbalDataExportRepository.
 */
#[ORM\Entity]
#[ORM\Table]
#[ORM\Index(name: 'idx_data_export_user', columns: ['user_id', 'requested_at'])]
#[ORM\Index(name: 'idx_data_export_expiry', columns: ['status', 'expires_at'])]
class DataExportEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: UuidType::NAME)]
        public Uuid $id,
        #[ORM\Column(name: 'user_id', type: UuidType::NAME)]
        public Uuid $userId,
        #[ORM\Column(type: 'string', length: 16)]
        public string $status,
        #[ORM\Column(name: 'requested_at', type: 'datetime_immutable')]
        public \DateTimeImmutable $requestedAt,
        #[ORM\Column(name: 'ready_at', type: 'datetime_immutable', nullable: true)]
        public ?\DateTimeImmutable $readyAt,
        #[ORM\Column(name: 'expires_at', type: 'datetime_immutable', nullable: true)]
        public ?\DateTimeImmutable $expiresAt,
        #[ORM\Column(name: 'failed_at', type: 'datetime_immutable', nullable: true)]
        public ?\DateTimeImmutable $failedAt,
    ) {
    }
}
