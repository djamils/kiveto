<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Command\ArchiveSupplier;

use App\Shared\Application\Bus\CommandInterface;

final readonly class ArchiveSupplier implements CommandInterface
{
    public function __construct(public string $supplierId)
    {
    }
}
