<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table]
#[ORM\Index(name: 'idx_pharma_jurisdiction_lookup', columns: ['jurisdiction_code', 'authority_code', 'authority_identifier'])]
class JurisdictionEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: AuthorizationEntity::class, inversedBy: 'jurisdictions')]
    #[ORM\JoinColumn(name: 'authorization_id', nullable: false, onDelete: 'CASCADE')]
    private AuthorizationEntity $authorization;

    #[ORM\Column(name: 'jurisdiction_code', type: 'string', length: 3)]
    private string $jurisdictionCode;

    #[ORM\Column(name: 'authority_code', type: 'string', length: 16)]
    private string $authorityCode;

    #[ORM\Column(name: 'authority_identifier', type: 'string', length: 64)]
    private string $authorityIdentifier;

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

    public function getJurisdictionCode(): string
    {
        return $this->jurisdictionCode;
    }

    public function setJurisdictionCode(string $jurisdictionCode): void
    {
        $this->jurisdictionCode = $jurisdictionCode;
    }

    public function getAuthorityCode(): string
    {
        return $this->authorityCode;
    }

    public function setAuthorityCode(string $authorityCode): void
    {
        $this->authorityCode = $authorityCode;
    }

    public function getAuthorityIdentifier(): string
    {
        return $this->authorityIdentifier;
    }

    public function setAuthorityIdentifier(string $authorityIdentifier): void
    {
        $this->authorityIdentifier = $authorityIdentifier;
    }
}
