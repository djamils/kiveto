<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Command\UpdateSupplierAccount;

use App\Shared\Application\Bus\CommandInterface;

final readonly class UpdateSupplierAccount implements CommandInterface
{
    /**
     * @param array<string, string|null>|null $billingAddress
     * @param array<string, string|null>|null $deliveryAddress
     */
    public function __construct(
        public string $accountId,
        public string $customerCode,
        public ?string $notes = null,
        public ?array $billingAddress = null,
        public ?array $deliveryAddress = null,
    ) {
    }
}
