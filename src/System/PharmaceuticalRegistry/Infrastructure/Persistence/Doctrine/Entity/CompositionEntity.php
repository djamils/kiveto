<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table]
class CompositionEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: AuthorizationEntity::class, inversedBy: 'compositions')]
    #[ORM\JoinColumn(name: 'authorization_id', nullable: false, onDelete: 'CASCADE')]
    private AuthorizationEntity $authorization;

    #[ORM\ManyToOne(targetEntity: ActiveSubstanceEntity::class)]
    #[ORM\JoinColumn(name: 'active_substance_id', nullable: false)]
    private ActiveSubstanceEntity $activeSubstance;

    #[ORM\Column(name: 'quantity_value', type: 'string', length: 32, nullable: true)]
    private ?string $quantityValue;

    #[ORM\Column(name: 'quantity_unit_label', type: 'string', length: 255, nullable: true)]
    private ?string $quantityUnitLabel;

    #[ORM\Column(name: 'quantity_unit_code', type: 'string', length: 32, nullable: true)]
    private ?string $quantityUnitCode;

    #[ORM\Column(name: 'is_excipient', type: 'boolean', options: ['default' => false])]
    private bool $isExcipient = false;

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function setId(Uuid $id): void
    {
        $this->id = $id;
    }

    public function getAuthorization(): AuthorizationEntity
    {
        return $this->authorization;
    }

    public function setAuthorization(AuthorizationEntity $authorization): void
    {
        $this->authorization = $authorization;
    }

    public function getActiveSubstance(): ActiveSubstanceEntity
    {
        return $this->activeSubstance;
    }

    public function setActiveSubstance(ActiveSubstanceEntity $activeSubstance): void
    {
        $this->activeSubstance = $activeSubstance;
    }

    public function getQuantityValue(): ?string
    {
        return $this->quantityValue;
    }

    public function setQuantityValue(?string $quantityValue): void
    {
        $this->quantityValue = $quantityValue;
    }

    public function getQuantityUnitLabel(): ?string
    {
        return $this->quantityUnitLabel;
    }

    public function setQuantityUnitLabel(?string $quantityUnitLabel): void
    {
        $this->quantityUnitLabel = $quantityUnitLabel;
    }

    public function getQuantityUnitCode(): ?string
    {
        return $this->quantityUnitCode;
    }

    public function setQuantityUnitCode(?string $quantityUnitCode): void
    {
        $this->quantityUnitCode = $quantityUnitCode;
    }

    public function isExcipient(): bool
    {
        return $this->isExcipient;
    }

    public function setIsExcipient(bool $isExcipient): void
    {
        $this->isExcipient = $isExcipient;
    }
}
