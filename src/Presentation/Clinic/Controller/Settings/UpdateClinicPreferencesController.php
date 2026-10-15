<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Settings;

use App\Context\Clinic\Application\Command\Staff\SetDefaultClinic\SetDefaultClinic;
use App\Presentation\Clinic\ViewModel\Settings\SettingsEndpoint;
use App\Presentation\Clinic\ViewModel\Settings\SettingsPageBuilder;
use App\Shared\Application\Bus\CommandBusInterface;
use App\System\IdentityAccess\Infrastructure\Security\Symfony\SecurityUser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * « Mes préférences »: the clinic the member lands in at sign-in. It only
 * concerns the member, so every member may change it — among the clinics they
 * belong to, never another.
 */
#[Route(path: '/settings/preferences', name: 'clinic_settings_preferences_save', methods: ['POST'])]
final readonly class UpdateClinicPreferencesController
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private SettingsEndpoint $endpoint,
        private SettingsPageBuilder $page,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        if (null !== $refused = $this->endpoint->guard($request, null)) {
            return $refused;
        }

        $user = $this->security->getUser();
        \assert($user instanceof SecurityUser);

        $clinicId = SettingsEndpoint::text(SettingsEndpoint::values($request), 'moi.clin');
        $mine     = array_column($this->page->preferences()['clinics'], 0);

        if (null === $clinicId || !\in_array($clinicId, $mine, true)) {
            return $this->endpoint->refuse('Choisissez une de vos cliniques.', 'moi.clin');
        }

        $this->commandBus->dispatch(new SetDefaultClinic(clinicId: $clinicId, userId: $user->id()));

        return $this->endpoint->ok();
    }
}
