<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Settings;

use App\Context\Clinic\Application\Command\Clinic\ChangeClinicLogo\ChangeClinicLogo;
use App\Presentation\Clinic\Security\ClinicPermissionGuard;
use App\Presentation\Clinic\ViewModel\Settings\SettingsEndpoint;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Takes the logo off the documents; they go back to the clinic's initial.
 */
#[Route(path: '/settings/logo/remove', name: 'clinic_settings_logo_remove', methods: ['POST'])]
final readonly class RemoveClinicLogoController
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

        $this->commandBus->dispatch(new ChangeClinicLogo(clinicId: $this->endpoint->clinicId(), logoKey: null));

        return $this->endpoint->ok();
    }
}
