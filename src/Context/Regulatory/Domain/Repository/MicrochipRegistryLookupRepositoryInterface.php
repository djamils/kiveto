<?php

declare(strict_types=1);

namespace App\Context\Regulatory\Domain\Repository;

use App\Context\Regulatory\Domain\MicrochipRegistryLookup;
use App\Context\Regulatory\Domain\ValueObject\MicrochipRegistryLookupId;

interface MicrochipRegistryLookupRepositoryInterface
{
    public function save(MicrochipRegistryLookup $lookup): void;

    public function findById(MicrochipRegistryLookupId $id): ?MicrochipRegistryLookup;
}
