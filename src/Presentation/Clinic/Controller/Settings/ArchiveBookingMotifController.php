<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Settings;

use App\Context\Scheduling\Application\Command\ArchiveBookingMotif\ArchiveBookingMotif;
use App\Context\Scheduling\Domain\Booking\BookingMotifNotFoundException;
use App\Presentation\Clinic\Security\ClinicPermissionGuard;
use App\Presentation\Clinic\ViewModel\Settings\SettingsEndpoint;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * Archives a motif of « Réservation en ligne »: no longer offered on the
 * Portal; the appointments already booked with it keep it.
 */
#[Route(path: '/settings/online-booking/motifs/{motifId}/archive', name: 'clinic_settings_booking_motif_archive', methods: ['POST'])]
final readonly class ArchiveBookingMotifController
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private SettingsEndpoint $endpoint,
    ) {
    }

    public function __invoke(Request $request, string $motifId): JsonResponse
    {
        if (null !== $refused = $this->endpoint->guard($request, ClinicPermissionGuard::MANAGE_STAFF)) {
            return $refused;
        }

        if (!Uuid::isValid($motifId)) {
            return $this->endpoint->refuse('Ce motif n\'existe plus : rechargez la page.');
        }

        try {
            $this->commandBus->dispatch(new ArchiveBookingMotif($this->endpoint->clinicId(), $motifId));
        } catch (BookingMotifNotFoundException) {
            return $this->endpoint->refuse('Ce motif n\'existe plus : rechargez la page.');
        }

        return $this->endpoint->ok();
    }
}
