<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Twig;

use App\Shared\Domain\Storage\FileStorageInterface;

/**
 * Inlines a stored logo as a data URI. Documents are rendered on their own and
 * sent to the PDF renderer, which cannot call the application back for the
 * image.
 */
final readonly class DocumentLogoRuntime
{
    /** Content type of a logo, by the extension of its stored file (any case). */
    public const array CONTENT_TYPES = [
        'png'  => 'image/png',
        'svg'  => 'image/svg+xml',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif'  => 'image/gif',
        'webp' => 'image/webp',
    ];

    public function __construct(
        private FileStorageInterface $storage,
    ) {
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

        $type = self::CONTENT_TYPES[mb_strtolower(pathinfo($logoKey, \PATHINFO_EXTENSION))] ?? 'image/png';

        return 'data:' . $type . ';base64,' . base64_encode($content);
    }
}
