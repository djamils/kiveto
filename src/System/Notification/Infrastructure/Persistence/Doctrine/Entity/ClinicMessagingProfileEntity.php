<?php

declare(strict_types=1);

namespace App\System\Notification\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Rows are read and written in SQL by
 * DbalClinicMessagingProfileRepository (upsert, row lock).
 */
#[ORM\Entity]
#[ORM\Table]
class ClinicMessagingProfileEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'clinic_id', type: UuidType::NAME)]
        private Uuid $clinicId,
        #[ORM\Column(type: 'string', length: 255)]
        private string $name,
        #[ORM\Column(name: 'email_local_part', type: 'string', length: 64)]
        private string $emailLocalPart,
        #[ORM\Column(name: 'reply_to', type: 'string', length: 320, nullable: true)]
        private ?string $replyTo,
        #[ORM\Column(name: 'sms_sender', type: 'string', length: 11)]
        private string $smsSender,
        #[ORM\Column(name: 'overage_cap_segments', type: 'integer')]
        private int $overageCapSegments,
        #[ORM\Column(name: 'address_line', type: 'string', length: 255)]
        private string $addressLine,
        #[ORM\Column(type: 'string', length: 120)]
        private string $city,
        #[ORM\Column(name: 'phone_display', type: 'string', length: 40)]
        private string $phoneDisplay,
        #[ORM\Column(name: 'brand_color', type: 'string', length: 7, nullable: true)]
        private ?string $brandColor,
        #[ORM\Column(name: 'logo_storage_key', type: 'string', length: 255, nullable: true)]
        private ?string $logoStorageKey,
        #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
        private \DateTimeImmutable $updatedAt,
    ) {
    }

    public function getClinicId(): Uuid
    {
        return $this->clinicId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEmailLocalPart(): string
    {
        return $this->emailLocalPart;
    }

    public function getReplyTo(): ?string
    {
        return $this->replyTo;
    }

    public function getSmsSender(): string
    {
        return $this->smsSender;
    }

    public function getOverageCapSegments(): int
    {
        return $this->overageCapSegments;
    }

    public function getAddressLine(): string
    {
        return $this->addressLine;
    }

    public function getCity(): string
    {
        return $this->city;
    }

    public function getPhoneDisplay(): string
    {
        return $this->phoneDisplay;
    }

    public function getBrandColor(): ?string
    {
        return $this->brandColor;
    }

    public function getLogoStorageKey(): ?string
    {
        return $this->logoStorageKey;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
