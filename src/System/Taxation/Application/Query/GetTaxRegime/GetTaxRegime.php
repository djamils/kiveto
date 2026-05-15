<?php

declare(strict_types=1);

namespace App\System\Taxation\Application\Query\GetTaxRegime;

use App\Shared\Application\Bus\QueryInterface;
use App\System\Taxation\Domain\ValueObject\TaxRegimeId;

final readonly class GetTaxRegime implements QueryInterface
{
    public function __construct(public readonly TaxRegimeId $regimeId)
    {
    }
}
