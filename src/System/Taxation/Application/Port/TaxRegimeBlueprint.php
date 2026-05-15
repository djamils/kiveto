<?php

declare(strict_types=1);

namespace App\System\Taxation\Application\Port;

use App\Shared\Domain\ValueObject\CountryCode;
use App\System\Taxation\Domain\Entity\MentionTemplate;
use App\System\Taxation\Domain\Entity\TaxRate;
use App\System\Taxation\Domain\ValueObject\TaxRegimeId;

final readonly class TaxRegimeBlueprint
{
    /**
     * @param list<TaxRate>         $rates
     * @param list<MentionTemplate> $mentionTemplates
     */
    public function __construct(
        public readonly TaxRegimeId $regimeId,
        public readonly string $name,
        public readonly CountryCode $country,
        public readonly bool $active,
        public readonly array $rates,
        public readonly array $mentionTemplates,
    ) {
    }
}
