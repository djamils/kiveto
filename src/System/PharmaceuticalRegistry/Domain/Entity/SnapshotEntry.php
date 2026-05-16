<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Domain\Entity;

use App\System\PharmaceuticalRegistry\Domain\ValueObject\ContentHash;

final class SnapshotEntry
{
    private string $authorityIdentifier;
    private ContentHash $contentHash;
    /** @var array<mixed> */
    private array $rawDto;

    /**
     * @param array<mixed> $rawDto
     */
    public function __construct(string $authorityIdentifier, ContentHash $contentHash, array $rawDto)
    {
        $this->authorityIdentifier = $authorityIdentifier;
        $this->contentHash         = $contentHash;
        $this->rawDto              = $rawDto;
    }

    public function authorityIdentifier(): string
    {
        return $this->authorityIdentifier;
    }

    public function contentHash(): ContentHash
    {
        return $this->contentHash;
    }

    /** @return array<mixed> */
    public function rawDto(): array
    {
        return $this->rawDto;
    }
}
