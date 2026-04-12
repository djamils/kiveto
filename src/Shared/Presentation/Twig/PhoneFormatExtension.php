<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class PhoneFormatExtension extends AbstractExtension
{
    public function __construct(
        private readonly PhoneFormatRuntime $runtime,
    ) {
    }

    /** @return list<TwigFilter> */
    public function getFilters(): array
    {
        return [
            new TwigFilter('phone_display', $this->runtime->phoneDisplay(...), ['is_safe' => ['html']]),
        ];
    }
}
