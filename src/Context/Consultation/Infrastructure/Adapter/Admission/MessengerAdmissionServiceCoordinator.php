<?php

declare(strict_types=1);

namespace App\Context\Consultation\Infrastructure\Adapter\Admission;

use App\Context\Admission\Application\Command\CloseAdmission\CloseAdmission;
use App\Context\Admission\Application\Command\UpdateAdmissionLocationStatus\UpdateAdmissionLocationStatus;
use App\Context\Consultation\Application\Port\AdmissionContextProviderInterface;
use App\Context\Consultation\Application\Port\AdmissionServiceCoordinatorInterface;
use App\Shared\Application\Bus\CommandBusInterface;

final readonly class MessengerAdmissionServiceCoordinator implements AdmissionServiceCoordinatorInterface
{
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

    public function closeAdmission(
        string $admissionId,
        string $clinicId,
        string $closureReason,
    ): void {
        // Closing twice is a domain error on the other side, and a second
        // consultation on the same visit is perfectly legitimate here.
        if (!$this->admissionContext->getAdmissionContext($admissionId)->isOpen) {
            return;
        }

        $this->commandBus->dispatch(new CloseAdmission(
            clinicId: $clinicId,
            admissionId: $admissionId,
            closureReason: $closureReason,
        ));
    }
}
