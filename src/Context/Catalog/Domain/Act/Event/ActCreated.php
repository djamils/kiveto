<?php

declare(strict_types=1);

namespace App\Context\Catalog\Domain\Act\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

final readonly class ActCreated extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'catalog';
    protected const int    VERSION         = 1;

    public function __construct(
        public string $actId,
        public string $clinicId,
        public string $name,
        public string $code,
        public string $category,
        public string $taxCategoryCode,
        public int $basePriceMinorUnits,
        public string $basePriceCurrency,
        public int $estimatedDurationMinutes,
        public bool $requiresAnesthesia,
    ) {
    }

    public function aggregateId(): string
    {
        return $this->actId;
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return [
            'actId'                    => $this->actId,
            'clinicId'                 => $this->clinicId,
            'name'                     => $this->name,
            'code'                     => $this->code,
            'category'                 => $this->category,
            'taxCategoryCode'          => $this->taxCategoryCode,
            'basePriceMinorUnits'      => $this->basePriceMinorUnits,
            'basePriceCurrency'        => $this->basePriceCurrency,
            'estimatedDurationMinutes' => $this->estimatedDurationMinutes,
            'requiresAnesthesia'       => $this->requiresAnesthesia,
        ];
    }
}
