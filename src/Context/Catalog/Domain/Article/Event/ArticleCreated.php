<?php

declare(strict_types=1);

namespace App\Context\Catalog\Domain\Article\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

final readonly class ArticleCreated extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'catalog';
    protected const int    VERSION         = 1;

    public function __construct(
        public string $articleId,
        public string $clinicId,
        public string $name,
        public string $code,
        public string $kind,
        public ?string $gtin,
        public string $taxCategoryCode,
        public int $basePriceMinorUnits,
        public string $basePriceCurrency,
        public string $unitOfMeasure,
        public bool $trackStock,
    ) {
    }

    public function aggregateId(): string
    {
        return $this->articleId;
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return [
            'articleId'           => $this->articleId,
            'clinicId'            => $this->clinicId,
            'name'                => $this->name,
            'code'                => $this->code,
            'kind'                => $this->kind,
            'gtin'                => $this->gtin,
            'taxCategoryCode'     => $this->taxCategoryCode,
            'basePriceMinorUnits' => $this->basePriceMinorUnits,
            'basePriceCurrency'   => $this->basePriceCurrency,
            'unitOfMeasure'       => $this->unitOfMeasure,
            'trackStock'          => $this->trackStock,
        ];
    }
}
