<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table]
#[ORM\Index(name: 'idx_pharma_snapshot_entry_authority', columns: ['authority_identifier'])]
class SnapshotEntryEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: SnapshotEntity::class, inversedBy: 'entries')]
    #[ORM\JoinColumn(name: 'snapshot_id', nullable: false, onDelete: 'CASCADE')]
    private SnapshotEntity $snapshot;

    #[ORM\Column(name: 'authority_identifier', type: 'string', length: 64)]
    private string $authorityIdentifier;

    #[ORM\Column(name: 'content_hash', type: 'string', length: 64, options: ['fixed' => true])]
    private string $contentHash;

    /** @var array<mixed> */
    #[ORM\Column(name: 'raw_dto', type: 'json')]
    private array $rawDto;

    #[ORM\Column(name: 'diff_kind', type: 'string', length: 16, nullable: true)]
    private ?string $diffKind;

    #[ORM\Column(name: 'target_uuid', type: UuidType::NAME, nullable: true)]
    private ?Uuid $targetUuid;

    /** @var array<mixed>|null */
    #[ORM\Column(name: 'changes', type: 'json', nullable: true)]
    private ?array $changes;

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function setId(Uuid $id): void
    {
        $this->id = $id;
    }

    public function getSnapshot(): SnapshotEntity
    {
        return $this->snapshot;
    }

    public function setSnapshot(SnapshotEntity $snapshot): void
    {
        $this->snapshot = $snapshot;
    }

    public function getAuthorityIdentifier(): string
    {
        return $this->authorityIdentifier;
    }

    public function setAuthorityIdentifier(string $authorityIdentifier): void
    {
        $this->authorityIdentifier = $authorityIdentifier;
    }

    public function getContentHash(): string
    {
        return $this->contentHash;
    }

    public function setContentHash(string $contentHash): void
    {
        $this->contentHash = $contentHash;
    }

    /** @return array<mixed> */
    public function getRawDto(): array
    {
        return $this->rawDto;
    }

    /** @param array<mixed> $rawDto */
    public function setRawDto(array $rawDto): void
    {
        $this->rawDto = $rawDto;
    }

    public function getDiffKind(): ?string
    {
        return $this->diffKind;
    }

    public function setDiffKind(?string $diffKind): void
    {
        $this->diffKind = $diffKind;
    }

    public function getTargetUuid(): ?Uuid
    {
        return $this->targetUuid;
    }

    public function setTargetUuid(?Uuid $targetUuid): void
    {
        $this->targetUuid = $targetUuid;
    }

    /** @return array<mixed>|null */
    public function getChanges(): ?array
    {
        return $this->changes;
    }

    /** @param array<mixed>|null $changes */
    public function setChanges(?array $changes): void
    {
        $this->changes = $changes;
    }
}
