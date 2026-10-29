<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * `clinic_logo_data_uri(logoKey)`: the clinic's logo inlined into a document.
 */
final class DocumentLogoExtension extends AbstractExtension
{
    public function __construct(
        private readonly DocumentLogoRuntime $runtime,
    ) {
    }

    /** @return list<TwigFunction> */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('clinic_logo_data_uri', $this->runtime->dataUri(...)),
        ];
    }
}
