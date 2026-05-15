<?php

declare(strict_types=1);

namespace App\System\Taxation\Application\Command\DeactivateTaxRegime;

use App\Shared\Application\Bus\CommandInterface;
use App\System\Taxation\Domain\ValueObject\TaxRegimeId;

final readonly class DeactivateTaxRegime implements CommandInterface
{
    public function __construct(public readonly TaxRegimeId $regimeId)
    {
    }
}
