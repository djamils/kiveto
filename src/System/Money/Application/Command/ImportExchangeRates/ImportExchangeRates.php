<?php

declare(strict_types=1);

namespace App\System\Money\Application\Command\ImportExchangeRates;

use App\Shared\Application\Bus\CommandInterface;

final readonly class ImportExchangeRates implements CommandInterface
{
    public function __construct(
        public readonly \DateTimeImmutable $date,
        public readonly string $source,
    ) {
    }
}
