<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Command\ValidateSupplierReceipt;

use App\Shared\Application\Bus\CommandInterface;

final readonly class ValidateSupplierReceipt implements CommandInterface
{
    public function __construct(
        public string $receiptId,
        public string $clinicId,
    ) {
    }
}
