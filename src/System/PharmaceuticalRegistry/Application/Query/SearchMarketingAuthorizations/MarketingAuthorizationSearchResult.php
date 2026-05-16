<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Application\Query\SearchMarketingAuthorizations;

readonly class MarketingAuthorizationSearchResult
{
    public function __construct(
        public string $id,
        public string $commercialName,
        public string $status,
        public ?string $atcVetCode,
        public ?string $gtin,
        public string $holderLaboratory,
    ) {
    }
}
