<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Command\RegisterSupplier;

use App\Shared\Application\Bus\CommandInterface;

final readonly class RegisterSupplier implements CommandInterface
{
    public function __construct(
        public string $name,
        public string $code,
        public string $type,
        public string $countryCode,
        public string $defaultCurrency,
        public string $integrationMode,
        public ?string $adapterIdentifier,
    ) {
    }
}
