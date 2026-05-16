<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Domain\Repository;

use App\System\PharmaceuticalRegistry\Domain\MarketingAuthorization;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\ImportSource;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\MarketingAuthorizationId;

interface MarketingAuthorizationRepositoryInterface
{
    public function save(MarketingAuthorization $ma): void;

    public function findById(MarketingAuthorizationId $id): ?MarketingAuthorization;

    public function findByJurisdictionalId(
        string $jurisdictionCode,
        string $authorityCode,
        string $authorityIdentifier,
    ): ?MarketingAuthorization;

    /**
     * @return iterable<MarketingAuthorizationHashProjection>
     */
    public function findProjectionsBySource(ImportSource $source): iterable;
}
