<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class ClinicSidebarExtension extends AbstractExtension
{
    public function __construct(
        private readonly ClinicSidebarRuntime $runtime,
    ) {
    }

    /** @return list<TwigFunction> */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('clinic_sidebar_data', $this->runtime->getSidebarData(...)),
        ];
    }
}
