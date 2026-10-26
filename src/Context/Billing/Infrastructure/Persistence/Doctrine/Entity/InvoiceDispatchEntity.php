<?php

declare(strict_types=1);

namespace App\Context\Billing\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Rows are read and written in SQL by DbalInvoiceDispatchRepository.
 */
#[ORM\Entity]
#[ORM\Table(name: 'billing__invoice_dispatches')]
#[ORM\Index(name: 'idx_billing_dispatch_invoice', columns: ['invoice_id', 'kind'])]
#[ORM\Index(name: 'idx_billing_dispatch_clinic_kind', columns: ['clinic_id', 'kind', 'created_at'])]
class InvoiceDispatchEntity
{
    /**
     * @param list<string> $channels
     * @param list<string> $notificationIds
     */
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: UuidType::NAME)]
        private Uuid $id,
        #[ORM\Column(name: 'clinic_id', type: UuidType::NAME)]
        private Uuid $clinicId,
        #[ORM\Column(name: 'invoice_id', type: UuidType::NAME)]
        private Uuid $invoiceId,
        #[ORM\Column(type: 'string', length: 16)]
        private string $kind,
        #[ORM\Column(type: 'json')]
        private array $channels,
        #[ORM\Column(name: 'notification_ids', type: 'json')]
        private array $notificationIds,
        #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
        private \DateTimeImmutable $createdAt,
        #[ORM\Column(name: 'author_user_id', type: UuidType::NAME, nullable: true)]
        private ?Uuid $authorUserId = null,
    ) {
    }

    /**
     * @return array{id: Uuid, clinic_id: Uuid, invoice_id: Uuid, kind: string, channels: list<string>, notification_ids: list<string>, created_at: \DateTimeImmutable, author_user_id: ?Uuid}
     */
    public function columns(): array
    {
        return [
            'id'               => $this->id,
            'clinic_id'        => $this->clinicId,
            'invoice_id'       => $this->invoiceId,
            'kind'             => $this->kind,
            'channels'         => $this->channels,
            'notification_ids' => $this->notificationIds,
            'created_at'       => $this->createdAt,
            'author_user_id'   => $this->authorUserId,
        ];
    }
}
