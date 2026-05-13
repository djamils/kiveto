<?php

declare(strict_types=1);

namespace App\System\Money\Application\Command\RegisterCurrency;

use App\Shared\Application\Bus\CommandInterface;

final readonly class RegisterCurrency implements CommandInterface
{
    public function __construct(
        public readonly string $code,
        public readonly string $symbol,
        public readonly int $decimals,
        public readonly string $displayName,
    ) {
    }
}
