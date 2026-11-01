<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Settings;

use App\Context\ClinicListing\Application\Command\UpdateListingSettings\UpdateListingSettings;
use App\Context\ClinicListing\Domain\Exception\InvalidListingSettingsException;
use App\Presentation\Clinic\Security\ClinicPermissionGuard;
use App\Presentation\Clinic\ViewModel\Settings\SettingsEndpoint;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * « Annuaire », saved whole on every change: `listed`, `acceptsNewClients`
 * and `tags` (a list of texts). ClinicListing checks the tags and names the
 * field it refuses; the directory follows at once.
 */
#[Route(path: '/settings/directory', name: 'clinic_settings_directory_save', methods: ['POST'])]
final readonly class UpdateDirectoryListingSettingsController
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private SettingsEndpoint $endpoint,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        if (null !== $refused = $this->endpoint->guard($request, ClinicPermissionGuard::MANAGE_STAFF)) {
            return $refused;
        }

        $values = SettingsEndpoint::values($request);
        $posted = $values['tags'] ?? [];
        $tags   = [];

        foreach (\is_array($posted) ? $posted : [$posted] as $tag) {
            if (!\is_string($tag)) {
                return $this->endpoint->refuse('Les tags doivent être des textes.', 'tags');
            }

            $tags[] = $tag;
        }

        try {
            $this->commandBus->dispatch(new UpdateListingSettings(
                clinicId: $this->endpoint->clinicId(),
                listed: true === ($values['listed'] ?? null),
                acceptsNewClients: true === ($values['acceptsNewClients'] ?? null),
                tags: $tags,
            ));
        } catch (InvalidListingSettingsException $e) {
            return $this->endpoint->refuse($e->getMessage(), $e->field);
        }

        return $this->endpoint->ok();
    }
}
