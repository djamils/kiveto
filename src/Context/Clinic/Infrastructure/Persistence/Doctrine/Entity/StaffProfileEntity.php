<?php

declare(strict_types=1);

namespace App\Context\Clinic\Infrastructure\Persistence\Doctrine\Entity;

use App\Context\Clinic\Domain\Staff\ValueObject\ProfessionalTitle;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table]
#[ORM\UniqueConstraint(name: 'uniq_staff_profile_membership', columns: ['membership_id'])]
#[ORM\Index(name: 'idx_staff_profile_membership_id', columns: ['membership_id'])]
class StaffProfileEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(name: 'membership_id', type: UuidType::NAME)]
    private Uuid $membershipId;

    #[ORM\Column(name: 'first_name', type: 'string', length: 80)]
    private string $firstName;

    #[ORM\Column(name: 'last_name', type: 'string', length: 80)]
    private string $lastName;

    #[ORM\Column(name: 'display_name', type: 'string', length: 60)]
    private string $displayName;

    #[ORM\Column(name: 'phone_number', type: 'string', length: 32, nullable: true)]
    private ?string $phoneNumber;

    #[ORM\Column(name: 'agenda_color', type: 'string', length: 7)]
    private string $agendaColor;

    #[ORM\Column(name: 'sort_order', type: 'integer', options: ['default' => 0])]
    private int $sortOrder;

    #[ORM\Column(name: 'is_visible_in_agenda', type: 'boolean', options: ['default' => true])]
    private bool $isVisibleInAgenda;

    #[ORM\Column(name: 'registration_number', type: 'string', length: 32, nullable: true)]
    private ?string $registrationNumber;

    #[ORM\Column(
        name: 'professional_title',
        type: 'string',
        length: 8,
        enumType: ProfessionalTitle::class,
        nullable: true,
    )]
    private ?ProfessionalTitle $professionalTitle;

    #[ORM\Column(name: 'signature_image_key', type: 'string', length: 255, nullable: true)]
    private ?string $signatureImageKey;

    #[ORM\Column(name: 'created_at_utc', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at_utc', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function setId(Uuid $id): void
    {
        $this->id = $id;
    }

    public function getMembershipId(): Uuid
    {
        return $this->membershipId;
    }

    public function setMembershipId(Uuid $membershipId): void
    {
        $this->membershipId = $membershipId;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): void
    {
        $this->firstName = $firstName;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): void
    {
        $this->lastName = $lastName;
    }

    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    public function setDisplayName(string $displayName): void
    {
        $this->displayName = $displayName;
    }

    public function getPhoneNumber(): ?string
    {
        return $this->phoneNumber;
    }

    public function setPhoneNumber(?string $phoneNumber): void
    {
        $this->phoneNumber = $phoneNumber;
    }

    public function getAgendaColor(): string
    {
        return $this->agendaColor;
    }

    public function setAgendaColor(string $agendaColor): void
    {
        $this->agendaColor = $agendaColor;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): void
    {
        $this->sortOrder = $sortOrder;
    }

    public function getIsVisibleInAgenda(): bool
    {
        return $this->isVisibleInAgenda;
    }

    public function setIsVisibleInAgenda(bool $isVisibleInAgenda): void
    {
        $this->isVisibleInAgenda = $isVisibleInAgenda;
    }

    public function getRegistrationNumber(): ?string
    {
        return $this->registrationNumber;
    }

    public function setRegistrationNumber(?string $registrationNumber): void
    {
        $this->registrationNumber = $registrationNumber;
    }

    public function getProfessionalTitle(): ?ProfessionalTitle
    {
        return $this->professionalTitle;
    }

    public function setProfessionalTitle(?ProfessionalTitle $professionalTitle): void
    {
        $this->professionalTitle = $professionalTitle;
    }

    public function getSignatureImageKey(): ?string
    {
        return $this->signatureImageKey;
    }

    public function setSignatureImageKey(?string $signatureImageKey): void
    {
        $this->signatureImageKey = $signatureImageKey;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }
}
