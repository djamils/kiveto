<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Port;

use App\Context\Catalog\Infrastructure\Adapter\HealthRegistry\PharmaceuticalRef;

interface PharmaceuticalRefProviderInterface
{
    public function findById(string $marketingAuthorizationId): ?PharmaceuticalRef;

    public function findByGtin(string $gtin): ?PharmaceuticalRef;

    /** @return list<PharmaceuticalRef> */
    public function searchByName(string $term, int $limit): array;
}
