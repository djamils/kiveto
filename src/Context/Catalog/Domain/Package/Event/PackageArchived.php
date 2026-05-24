<?php

declare(strict_types=1);

namespace App\Context\Catalog\Domain\Package\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

final readonly class PackageArchived extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'catalog';
    protected const int    VERSION         = 1;

    public function __construct(
        public string $packageId,
        public string $clinicId,
    ) {
    }

    public function aggregateId(): string
    {
        return $this->packageId;
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return [
            'packageId' => $this->packageId,
            'clinicId'  => $this->clinicId,
        ];
    }
}
