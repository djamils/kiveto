<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Settings;

use App\Context\Scheduling\Application\Command\SaveBookingMotif\SaveBookingMotif;
use App\Context\Scheduling\Domain\Booking\BookingMotifNotFoundException;
use App\Context\Scheduling\Domain\Booking\InvalidBookingConfigurationException;
use App\Presentation\Clinic\Security\ClinicPermissionGuard;
use App\Presentation\Clinic\ViewModel\Settings\SettingsEndpoint;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * Creates (no `id`) or updates a motif of « Réservation en ligne »; answers
 * its id. Scheduling checks the motif and names the field it refuses.
 */
#[Route(path: '/settings/online-booking/motifs', name: 'clinic_settings_booking_motif_save', methods: ['POST'])]
final readonly class SaveBookingMotifController
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

        $values  = SettingsEndpoint::values($request);
        $motifId = SettingsEndpoint::text($values, 'id');

        if (null !== $motifId && !Uuid::isValid($motifId)) {
            return $this->endpoint->refuse('Ce motif n\'existe plus : rechargez la page.');
        }

        $duration = SettingsEndpoint::int($values, 'durationMin');

        if (null === $duration) {
            return $this->endpoint->refuse('Indiquez la durée du motif.', 'durationMin');
        }

        try {
            $id = $this->commandBus->dispatch(new SaveBookingMotif(
                clinicId: $this->endpoint->clinicId(),
                motifId: $motifId,
                label: SettingsEndpoint::text($values, 'label') ?? '',
                description: SettingsEndpoint::text($values, 'description'),
                durationMin: $duration,
                onlineEnabled: true === ($values['onlineEnabled'] ?? null),
                confirmationKnownClient: SettingsEndpoint::text($values, 'confirmationKnownClient') ?? '',
                allowedBlockTypes: self::strings($values['allowedBlockTypes'] ?? null),
                practitionerIds: self::strings($values['practitionerIds'] ?? null),
                preventiveCareKind: SettingsEndpoint::text($values, 'preventiveCareKind'),
                newClientsAllowed: true === ($values['newClientsAllowed'] ?? null),
                sortOrder: SettingsEndpoint::int($values, 'sortOrder') ?? 0,
            ));
        } catch (InvalidBookingConfigurationException $e) {
            return $this->endpoint->refuse($e->getMessage(), $e->field);
        } catch (BookingMotifNotFoundException) {
            return $this->endpoint->refuse('Ce motif n\'existe plus : rechargez la page.');
        } catch (\InvalidArgumentException) {
            // A practitioner id that is not one.
            return $this->endpoint->refuse('Ce praticien ne peut pas prendre de rendez-vous dans cette clinique.', 'practitionerIds');
        }

        return $this->endpoint->ok(['id' => \is_string($id) ? $id : $motifId]);
    }

    /**
     * @return list<string>
     */
    private static function strings(mixed $raw): array
    {
        if (!\is_array($raw)) {
            return [];
        }

        return array_values(array_unique(array_filter($raw, \is_string(...))));
    }
}
