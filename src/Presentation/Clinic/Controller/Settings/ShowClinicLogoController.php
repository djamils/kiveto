<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Settings;

use App\Presentation\Clinic\ViewModel\Settings\SettingsEndpoint;
use App\Shared\Domain\Storage\FileStorageInterface;
use App\Shared\Presentation\Twig\DocumentLogoRuntime;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The logo of the current clinic, as an image. The stored file is never named
 * in the URL: the route only ever serves the logo the clinic points at.
 */
#[Route(path: '/settings/logo', name: 'clinic_settings_logo', methods: ['GET'])]
final readonly class ShowClinicLogoController
{
    public function __construct(
        private SettingsEndpoint $endpoint,
        private FileStorageInterface $storage,
    ) {
    }

    public function __invoke(): Response
    {
        $key = $this->endpoint->settings()->logoKey;

        if (null === $key) {
            throw new NotFoundHttpException('Cette clinique n’a pas de logo.');
        }

        try {
            $content = $this->storage->read($key);
        } catch (\RuntimeException) {
            throw new NotFoundHttpException('Le fichier du logo est introuvable.');
        }

        return new Response($content, Response::HTTP_OK, [
            'Content-Type'  => DocumentLogoRuntime::CONTENT_TYPES[pathinfo($key, \PATHINFO_EXTENSION)] ?? 'application/octet-stream',
            'Cache-Control' => 'private, max-age=86400',
            // An SVG opened on its own must not run anything.
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'",
            'X-Content-Type-Options'  => 'nosniff',
        ]);
    }
}
