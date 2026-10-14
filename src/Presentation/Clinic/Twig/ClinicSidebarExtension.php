<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Twig;

use App\Presentation\Clinic\ViewModel\Shell\ShellViewBuilder;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class ClinicSidebarExtension extends AbstractExtension
{
    public function __construct(
        private readonly ClinicSidebarRuntime $runtime,
        private readonly ShellViewBuilder $shellViewBuilder,
    ) {
    }

    /** @return list<TwigFunction> */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('clinic_can', $this->runtime->can(...)),
            new TwigFunction('clinic_shell', $this->shellViewBuilder->build(...)),
        ];
    }
}
