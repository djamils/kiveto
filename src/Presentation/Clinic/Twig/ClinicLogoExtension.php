<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Twig;

use App\Presentation\Clinic\Controller\Settings\ShowClinicLogoController;
use App\Shared\Domain\Storage\FileStorageInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * `clinic_logo_data_uri(logoKey)`: the clinic's logo inlined into a document.
 * Documents are rendered on their own and sent to the PDF renderer, which
 * cannot call the application back for the image.
 */
final class ClinicLogoExtension extends AbstractExtension
{
    public function __construct(
        private readonly FileStorageInterface $storage,
    ) {
    }

    /** @return list<TwigFunction> */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('clinic_logo_data_uri', $this->dataUri(...)),
        ];
    }

    public function dataUri(?string $logoKey): ?string
    {
        if (null === $logoKey) {
            return null;
        }

        try {
            $content = $this->storage->read($logoKey);
        } catch (\RuntimeException) {
            // A logo whose file went missing prints as no logo at all.
            return null;
        }

        $type = ShowClinicLogoController::CONTENT_TYPES[pathinfo($logoKey, \PATHINFO_EXTENSION)] ?? 'image/png';

        return 'data:' . $type . ';base64,' . base64_encode($content);
    }
}
