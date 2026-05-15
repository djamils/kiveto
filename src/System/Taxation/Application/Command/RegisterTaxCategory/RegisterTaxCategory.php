<?php

declare(strict_types=1);

namespace App\System\Taxation\Application\Command\RegisterTaxCategory;

use App\Shared\Application\Bus\CommandInterface;
use App\System\Taxation\Domain\ValueObject\TaxCategoryCode;

final readonly class RegisterTaxCategory implements CommandInterface
{
    public function __construct(
        public readonly TaxCategoryCode $code,
        public readonly string $displayName,
        public readonly ?string $description,
    ) {
    }
}
