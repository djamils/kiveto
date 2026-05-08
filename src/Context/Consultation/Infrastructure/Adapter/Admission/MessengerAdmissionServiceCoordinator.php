<?php

declare(strict_types=1);

namespace App\Context\Consultation\Infrastructure\Adapter\Admission;

use App\Context\Admission\Application\Command\UpdateAdmissionLocationStatus\UpdateAdmissionLocationStatus;
use App\Context\Consultation\Application\Port\AdmissionServiceCoordinatorInterface;
use App\Shared\Application\Bus\CommandBusInterface;

final readonly class MessengerAdmissionServiceCoordinator implements AdmissionServiceCoordinatorInterface
{
    public function __construct(
        private CommandBusInterface $commandBus,
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
}
