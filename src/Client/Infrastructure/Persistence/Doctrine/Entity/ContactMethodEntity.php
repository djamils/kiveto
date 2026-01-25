<?php

declare(strict_types=1);

namespace App\Client\Infrastructure\Persistence\Doctrine\Entity;

use App\Client\Domain\ValueObject\ContactLabel;
use App\Client\Domain\ValueObject\ContactMethodType;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table]
#[ORM\Index(name: 'idx_contact_method_client_id', columns: ['client_id'])]
#[ORM\Index(name: 'idx_contact_method_type', columns: ['type'])]
class ContactMethodEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(name: 'client_id', type: UuidType::NAME)]
    private Uuid $clientId;

    #[ORM\Column(type: 'string', length: 20, enumType: ContactMethodType::class)]
    private ContactMethodType $type;

    #[ORM\Column(type: 'string', length: 20, enumType: ContactLabel::class)]
    private ContactLabel $label;

    #[ORM\Column(type: 'string', length: 255)]
    private string $value;

    #[ORM\Column(type: 'boolean')]
    private bool $isPrimary;

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function setId(Uuid $id): void
    {
        $this->id = $id;
    }

    public function getClientId(): Uuid
    {
        return $this->clientId;
    }

    public function setClientId(Uuid $clientId): void
    {
        $this->clientId = $clientId;
    }

    public function getType(): ContactMethodType
    {
        return $this->type;
    }

    public function setType(ContactMethodType $type): void
    {
        $this->type = $type;
    }

    public function getLabel(): ContactLabel
    {
        return $this->label;
    }

    public function setLabel(ContactLabel $label): void
    {
        $this->label = $label;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function setValue(string $value): void
    {
        $this->value = $value;
    }

    public function isPrimary(): bool
    {
        return $this->isPrimary;
    }

    public function setIsPrimary(bool $isPrimary): void
    {
        $this->isPrimary = $isPrimary;
    }
}
