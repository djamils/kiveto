<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Command\DisableSupplierAccount;

use App\Shared\Application\Bus\CommandInterface;

final readonly class DisableSupplierAccount implements CommandInterface
{
    public function __construct(public string $accountId)
    {
    }
}
