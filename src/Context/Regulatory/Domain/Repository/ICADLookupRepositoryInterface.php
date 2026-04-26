<?php

declare(strict_types=1);

namespace App\Context\Regulatory\Domain\Repository;

use App\Context\Regulatory\Domain\ICADLookup;
use App\Context\Regulatory\Domain\ValueObject\ICADLookupId;

interface ICADLookupRepositoryInterface
{
    public function save(ICADLookup $lookup): void;

    public function findById(ICADLookupId $id): ?ICADLookup;
}
