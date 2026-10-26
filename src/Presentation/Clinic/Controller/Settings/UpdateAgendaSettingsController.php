<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Settings;

use App\Context\Scheduling\Application\Command\ChangeReminderDelay\ChangeReminderDelay;
use App\Presentation\Clinic\Security\ClinicPermissionGuard;
use App\Presentation\Clinic\ViewModel\Settings\SettingsEndpoint;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The « Agenda » section, saved whole on every change: the reminder delay.
 * Scheduling replans the reminders not sent yet of the appointments to come.
 */
#[Route(path: '/settings/agenda', name: 'clinic_settings_agenda_save', methods: ['POST'])]
final readonly class UpdateAgendaSettingsController
{
    private const array DELAYS = ['H24', 'H48', 'SAME_DAY_8'];

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

        $delay = SettingsEndpoint::text(SettingsEndpoint::values($request), 'ag.rappel');

        if (null === $delay || !\in_array($delay, self::DELAYS, true)) {
            return $this->endpoint->refuse('Choisissez quand le rappel est envoyé.', 'ag.rappel');
        }

        $this->commandBus->dispatch(new ChangeReminderDelay($this->endpoint->clinicId(), $delay));

        return $this->endpoint->ok();
    }
}
