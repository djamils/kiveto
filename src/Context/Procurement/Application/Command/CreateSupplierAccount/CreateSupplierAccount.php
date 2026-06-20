<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Command\CreateSupplierAccount;

use App\Shared\Application\Bus\CommandInterface;

final readonly class CreateSupplierAccount implements CommandInterface
{
    public function __construct(
        public string $clinicId,
        public string $supplierId,
        public string $customerCode,
        public ?string $notes = null,
    ) {
    }
}
