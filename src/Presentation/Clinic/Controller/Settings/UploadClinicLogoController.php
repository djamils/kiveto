<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Settings;

use App\Context\Clinic\Application\Command\Clinic\ChangeClinicLogo\ChangeClinicLogo;
use App\Presentation\Clinic\Security\ClinicPermissionGuard;
use App\Presentation\Clinic\ViewModel\Settings\SettingsEndpoint;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Domain\Storage\FileStorageInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * « Remplacer » the logo: the file is checked, stored, and the clinic is pointed
 * at it. The documents print it from then on.
 */
#[Route(path: '/settings/logo', name: 'clinic_settings_logo_upload', methods: ['POST'])]
final readonly class UploadClinicLogoController
{
    private const int MAX_BYTES = 1_048_576;

    /** What a logo may be, by the type its content shows. */
    private const array TYPES = ['image/png' => 'png', 'image/svg+xml' => 'svg', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];

    public function __construct(
        private CommandBusInterface $commandBus,
        private SettingsEndpoint $endpoint,
        private FileStorageInterface $storage,
        private UrlGeneratorInterface $urls,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        if (null !== $refused = $this->endpoint->guard($request, ClinicPermissionGuard::MANAGE_STAFF)) {
            return $refused;
        }

        $file = $request->files->get('logo');

        if (!$file instanceof UploadedFile || !$file->isValid()) {
            return $this->endpoint->refuse('Aucun fichier reçu.', 'id.logo');
        }

        if ($file->getSize() > self::MAX_BYTES) {
            return $this->endpoint->refuse('Le logo ne doit pas dépasser 1 Mo.', 'id.logo');
        }

        $type      = $file->getMimeType() ?? '';
        $extension = self::TYPES[$type] ?? null;
        $content   = $file->getContent();

        // An SVG is a document, not a picture: one that runs code is refused.
        if ('svg' === $extension && 1 === preg_match('/<script|\bon\w+\s*=|javascript:/i', $content)) {
            $extension = null;
        }

        if (null === $extension) {
            return $this->endpoint->refuse('Choisissez une image PNG, SVG, JPEG ou WebP.', 'id.logo');
        }

        $clinicId = $this->endpoint->clinicId();
        $key      = $this->storage->store(\sprintf('clinic-logos/%s-%s.%s', $clinicId, bin2hex(random_bytes(6)), $extension), $content);

        $this->commandBus->dispatch(new ChangeClinicLogo(clinicId: $clinicId, logoKey: $key));

        return $this->endpoint->ok([
            'url' => $this->urls->generate('clinic_settings_logo', ['v' => mb_substr(md5($key), 0, 8)]),
        ]);
    }
}
