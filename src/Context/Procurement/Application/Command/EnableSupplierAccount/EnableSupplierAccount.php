<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Command\EnableSupplierAccount;

use App\Shared\Application\Bus\CommandInterface;

final readonly class EnableSupplierAccount implements CommandInterface
{
    public function __construct(public string $accountId)
    {
    }
}
