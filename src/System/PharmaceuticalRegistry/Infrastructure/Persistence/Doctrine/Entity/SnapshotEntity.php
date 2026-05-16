<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table]
#[ORM\Index(name: 'idx_pharma_snapshot_source_date', columns: ['source', 'downloaded_at'])]
class SnapshotEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(name: 'source', type: 'string', length: 16)]
    private string $source;

    #[ORM\Column(name: 'status', type: 'string', length: 16)]
    private string $status;

    #[ORM\Column(name: 'downloaded_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $downloadedAt;

    #[ORM\Column(name: 'applied_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $appliedAt;

    #[ORM\Column(name: 'error_message', type: 'text', nullable: true)]
    private ?string $errorMessage;

    /** @var Collection<int, SnapshotEntryEntity> */
    #[ORM\OneToMany(
        targetEntity: SnapshotEntryEntity::class,
        mappedBy: 'snapshot',
        cascade: ['persist', 'remove'],
        orphanRemoval: true,
    )]
    private Collection $entries;

    public function __construct()
    {
        $this->entries = new ArrayCollection();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function setId(Uuid $id): void
    {
        $this->id = $id;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function setSource(string $source): void
    {
        $this->source = $source;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getDownloadedAt(): \DateTimeImmutable
    {
        return $this->downloadedAt;
    }

    public function setDownloadedAt(\DateTimeImmutable $downloadedAt): void
    {
        $this->downloadedAt = $downloadedAt;
    }

    public function getAppliedAt(): ?\DateTimeImmutable
    {
        return $this->appliedAt;
    }

    public function setAppliedAt(?\DateTimeImmutable $appliedAt): void
    {
        $this->appliedAt = $appliedAt;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): void
    {
        $this->errorMessage = $errorMessage;
    }

    /** @return Collection<int, SnapshotEntryEntity> */
    public function getEntries(): Collection
    {
        return $this->entries;
    }

    public function addEntry(SnapshotEntryEntity $entry): void
    {
        $this->entries->add($entry);
    }
}
