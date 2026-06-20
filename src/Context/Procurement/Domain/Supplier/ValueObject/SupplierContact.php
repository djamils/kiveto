<?php

declare(strict_types=1);

namespace App\Context\Procurement\Domain\Supplier\ValueObject;

use App\Context\Procurement\Domain\Shared\ValueObject\Address;

final readonly class SupplierContact
{
    private function __construct(
        public readonly ?string $email,
        public readonly ?string $phone,
        public readonly ?string $contactPerson,
        public readonly ?Address $address,
    ) {
    }

    public static function create(?string $email, ?string $phone, ?string $contactPerson, ?Address $address): self
    {
        return new self($email, $phone, $contactPerson, $address);
    }
}
