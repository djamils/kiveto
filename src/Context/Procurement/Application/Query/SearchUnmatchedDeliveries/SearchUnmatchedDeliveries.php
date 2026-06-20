<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Query\SearchUnmatchedDeliveries;

use App\Shared\Application\Bus\QueryInterface;

final readonly class SearchUnmatchedDeliveries implements QueryInterface
{
    public function __construct(public string $clinicId)
    {
    }
}
