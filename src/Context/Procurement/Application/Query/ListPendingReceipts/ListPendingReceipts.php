<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Query\ListPendingReceipts;

use App\Shared\Application\Bus\QueryInterface;

final readonly class ListPendingReceipts implements QueryInterface
{
    public function __construct(public string $clinicId)
    {
    }
}
