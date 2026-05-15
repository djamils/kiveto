<?php

declare(strict_types=1);

namespace App\System\Taxation\Application\Query\ListTaxCategories;

use App\Shared\Application\Bus\QueryInterface;

final readonly class ListTaxCategories implements QueryInterface
{
    public function __construct(public readonly ?string $prefix = null)
    {
    }
}
