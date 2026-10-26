<?php

declare(strict_types=1);

namespace App\Context\Inventory\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * A lot announced « Péremption proche ». Rows are read and written in SQL by
 * DbalExpiryNoticeRepository.
 */
#[ORM\Entity]
#[ORM\Table(name: 'inventory__expiry_notifications')]
class ExpiryNotificationEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'lot_id', type: UuidType::NAME)]
        private Uuid $lotId,
        #[ORM\Column(name: 'notified_at', type: 'datetime_immutable')]
        private \DateTimeImmutable $notifiedAt,
    ) {
    }

    /**
     * @return array{lot_id: Uuid, notified_at: \DateTimeImmutable}
     */
    public function columns(): array
    {
        return ['lot_id' => $this->lotId, 'notified_at' => $this->notifiedAt];
    }
}
