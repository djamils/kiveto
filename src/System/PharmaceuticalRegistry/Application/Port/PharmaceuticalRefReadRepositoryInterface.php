<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Application\Port;

use App\System\PharmaceuticalRegistry\Application\ReadModel\PharmaceuticalRefView;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\Gtin;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\MarketingAuthorizationId;

interface PharmaceuticalRefReadRepositoryInterface
{
    public function findById(MarketingAuthorizationId $id): ?PharmaceuticalRefView;

    public function findByGtin(Gtin $gtin): ?PharmaceuticalRefView;

    public function findByJurisdictionalId(
        string $jurisdiction,
        string $authority,
        string $identifier,
    ): ?PharmaceuticalRefView;
}
