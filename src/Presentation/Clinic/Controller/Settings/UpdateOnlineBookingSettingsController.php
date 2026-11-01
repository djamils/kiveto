<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Settings;

use App\Context\Scheduling\Application\Command\UpdateOnlineBookingSettings\UpdateOnlineBookingSettings;
use App\Context\Scheduling\Domain\Booking\InvalidBookingConfigurationException;
use App\Presentation\Clinic\Security\ClinicPermissionGuard;
use App\Presentation\Clinic\ViewModel\Settings\SettingsEndpoint;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The general settings of « Réservation en ligne », saved whole on every
 * change. Scheduling checks the bounds and names the field it refuses.
 */
#[Route(path: '/settings/online-booking', name: 'clinic_settings_online_booking_save', methods: ['POST'])]
final readonly class UpdateOnlineBookingSettingsController
{
    /** Number fields → the French reason when one is missing. */
    private const array NUMBERS = [
        'gridStepMinutes'         => 'Choisissez le pas de la grille.',
        'minLeadTimeMinutes'      => 'Indiquez le délai minimum de réservation.',
        'minLeadTimeManualHours'  => 'Indiquez le délai minimum d\'une demande à confirmer.',
        'horizonDays'             => 'Indiquez l\'horizon de réservation.',
        'cancellationWindowHours' => 'Indiquez le délai d\'annulation.',
    ];

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

        $values  = SettingsEndpoint::values($request);
        $numbers = [];

        foreach (self::NUMBERS as $field => $missing) {
            $number = SettingsEndpoint::int($values, $field);

            if (null === $number) {
                return $this->endpoint->refuse($missing, $field);
            }

            $numbers[$field] = $number;
        }

        try {
            $this->commandBus->dispatch(new UpdateOnlineBookingSettings(
                clinicId: $this->endpoint->clinicId(),
                enabled: true === ($values['enabled'] ?? null),
                gridStepMinutes: $numbers['gridStepMinutes'],
                minLeadTimeMinutes: $numbers['minLeadTimeMinutes'],
                minLeadTimeManualHours: $numbers['minLeadTimeManualHours'],
                horizonDays: $numbers['horizonDays'],
                cancellationWindowHours: $numbers['cancellationWindowHours'],
                newClientBookingEnabled: true === ($values['newClientBookingEnabled'] ?? null),
            ));
        } catch (InvalidBookingConfigurationException $e) {
            return $this->endpoint->refuse($e->getMessage(), $e->field);
        }

        return $this->endpoint->ok();
    }
}
