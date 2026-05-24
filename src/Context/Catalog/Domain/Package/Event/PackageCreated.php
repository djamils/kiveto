<?php

declare(strict_types=1);

namespace App\Context\Catalog\Domain\Package\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

final readonly class PackageCreated extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'catalog';
    protected const int    VERSION         = 1;

    public function __construct(
        public string $packageId,
        public string $clinicId,
        public string $name,
        public string $code,
        public string $pricingMode,
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
            'packageId'   => $this->packageId,
            'clinicId'    => $this->clinicId,
            'name'        => $this->name,
            'code'        => $this->code,
            'pricingMode' => $this->pricingMode,
        ];
    }
}
