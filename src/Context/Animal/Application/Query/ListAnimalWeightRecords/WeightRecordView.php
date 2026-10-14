<?php

declare(strict_types=1);

namespace App\Context\Animal\Application\Query\ListAnimalWeightRecords;

final readonly class WeightRecordView
{
    public function __construct(
        public string $id,
        public int $weightGrams,
        public ?int $bodyConditionScore,
        public \DateTimeImmutable $measuredOn,
        public string $authorName,
    ) {
    }
}
