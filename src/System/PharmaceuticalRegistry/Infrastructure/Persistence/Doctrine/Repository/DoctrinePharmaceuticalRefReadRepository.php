<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Infrastructure\Persistence\Doctrine\Repository;

use App\System\PharmaceuticalRegistry\Application\Port\PharmaceuticalRefReadRepositoryInterface;
use App\System\PharmaceuticalRegistry\Application\ReadModel\PharmaceuticalRefView;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\Gtin;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\MarketingAuthorizationId;
use App\System\PharmaceuticalRegistry\Infrastructure\ReadModel\PharmaceuticalRefProjection;

final readonly class DoctrinePharmaceuticalRefReadRepository implements PharmaceuticalRefReadRepositoryInterface
{
    public function __construct(
        private PharmaceuticalRefProjection $projection,
    ) {
    }

    public function findById(MarketingAuthorizationId $id): ?PharmaceuticalRefView
    {
        return $this->projection->findById($id->toString());
    }

    public function findByGtin(Gtin $gtin): ?PharmaceuticalRefView
    {
        return $this->projection->findByGtin($gtin->toString());
    }

    public function findByJurisdictionalId(string $jurisdiction, string $authority, string $identifier): ?PharmaceuticalRefView
    {
        return $this->projection->findByJurisdictionalId($jurisdiction, $authority, $identifier);
    }
}
