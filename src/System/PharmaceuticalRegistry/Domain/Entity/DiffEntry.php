<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Domain\Entity;

use App\System\PharmaceuticalRegistry\Domain\ValueObject\DiffKind;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\MarketingAuthorizationId;

final class DiffEntry
{
    private string $authorityIdentifier;
    private DiffKind $diffKind;
    private ?MarketingAuthorizationId $targetUuid;
    /** @var array<mixed>|null */
    private ?array $changes;
    /** @var array<mixed>|null */
    private ?array $rawDto;

    /**
     * @param array<mixed>|null $changes
     * @param array<mixed>|null $rawDto
     */
    public function __construct(
        string $authorityIdentifier,
        DiffKind $diffKind,
        ?MarketingAuthorizationId $targetUuid,
        ?array $changes = null,
        ?array $rawDto = null,
    ) {
        $this->authorityIdentifier = $authorityIdentifier;
        $this->diffKind            = $diffKind;
        $this->targetUuid          = $targetUuid;
        $this->changes             = $changes;
        $this->rawDto              = $rawDto;
    }

    public function authorityIdentifier(): string
    {
        return $this->authorityIdentifier;
    }

    public function diffKind(): DiffKind
    {
        return $this->diffKind;
    }

    public function targetUuid(): ?MarketingAuthorizationId
    {
        return $this->targetUuid;
    }

    /** @return array<mixed>|null */
    public function changes(): ?array
    {
        return $this->changes;
    }

    /** @return array<mixed>|null */
    public function rawDto(): ?array
    {
        return $this->rawDto;
    }
}
