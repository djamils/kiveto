<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class MoneyFormatExtension extends AbstractExtension
{
    public function __construct(
        private readonly MoneyFormatRuntime $runtime,
    ) {
    }

    /** @return list<TwigFilter> */
    public function getFilters(): array
    {
        return [
            new TwigFilter('money', $this->runtime->moneyFormat(...)),
        ];
    }
}
