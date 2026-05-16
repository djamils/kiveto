<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Domain\Repository;

use App\System\PharmaceuticalRegistry\Domain\ValueObject\ContentHash;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\MarketingAuthorizationId;

final readonly class MarketingAuthorizationHashProjection
{
    public function __construct(
        public string $authorityIdentifier,
        public ContentHash $contentHash,
        public MarketingAuthorizationId $id,
    ) {
    }
}
