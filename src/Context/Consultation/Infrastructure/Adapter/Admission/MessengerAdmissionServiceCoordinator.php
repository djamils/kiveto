<?php

declare(strict_types=1);

namespace App\Context\Consultation\Infrastructure\Adapter\Admission;

use App\Context\Admission\Application\Command\UpdateAdmissionLocationStatus\UpdateAdmissionLocationStatus;
use App\Context\Consultation\Application\Port\AdmissionContextProviderInterface;
use App\Context\Consultation\Application\Port\AdmissionServiceCoordinatorInterface;
use App\Shared\Application\Bus\CommandBusInterface;

final readonly class MessengerAdmissionServiceCoordinator implements AdmissionServiceCoordinatorInterface
{
    private const string IN_CONSULTATION_ROOM = 'in_consultation_room';
    private const string AT_RECEPTION         = 'at_reception';

    public function __construct(
        private CommandBusInterface $commandBus,
        private AdmissionContextProviderInterface $admissionContext,
    ) {
    }

    public function updateLocationStatus(
        string $admissionId,
        string $newLocationStatus,
        string $clinicId,
    ): void {
        $this->commandBus->dispatch(new UpdateAdmissionLocationStatus(
            clinicId: $clinicId,
            admissionId: $admissionId,
            newLocationStatus: $newLocationStatus,
        ));
    }

    public function sendToReception(string $admissionId, string $clinicId): void
    {
        $context = $this->admissionContext->getAdmissionContext($admissionId);

        if (!$context->isOpen || self::IN_CONSULTATION_ROOM !== $context->locationStatus) {
            return;
        }

        $this->updateLocationStatus($admissionId, self::AT_RECEPTION, $clinicId);
    }
}
