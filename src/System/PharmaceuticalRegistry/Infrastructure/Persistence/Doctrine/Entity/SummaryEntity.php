<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table]
class SummaryEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\OneToOne(targetEntity: AuthorizationEntity::class, inversedBy: 'summary')]
    #[ORM\JoinColumn(name: 'authorization_id', unique: true, nullable: false, onDelete: 'CASCADE')]
    private AuthorizationEntity $authorization;

    /** @var array<int, array{titleCode: int, titleLabel: string, content: string}> */
    #[ORM\Column(name: 'sections', type: 'json')]
    private array $sections = [];

    #[ORM\Column(name: 'source_url', type: 'string', length: 512, nullable: true)]
    private ?string $sourceUrl;

    #[ORM\Column(name: 'last_updated_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastUpdatedAt;

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

    /** @return array<int, array{titleCode: int, titleLabel: string, content: string}> */
    public function getSections(): array
    {
        return $this->sections;
    }

    /** @param array<int, array{titleCode: int, titleLabel: string, content: string}> $sections */
    public function setSections(array $sections): void
    {
        $this->sections = $sections;
    }

    public function getSourceUrl(): ?string
    {
        return $this->sourceUrl;
    }

    public function setSourceUrl(?string $sourceUrl): void
    {
        $this->sourceUrl = $sourceUrl;
    }

    public function getLastUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->lastUpdatedAt;
    }

    public function setLastUpdatedAt(?\DateTimeImmutable $lastUpdatedAt): void
    {
        $this->lastUpdatedAt = $lastUpdatedAt;
    }
}
