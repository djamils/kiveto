<?php

declare(strict_types=1);

namespace App\Context\Billing\Infrastructure\Adapter\Admission;

use App\Context\Admission\Application\Command\CloseAdmission\CloseAdmission;
use App\Context\Billing\Application\Port\AdmissionCoordinatorInterface;
use App\Shared\Application\Bus\CommandBusInterface;

/**
 * Ends the visit once it has been paid for.
 *
 * A visit that is already closed — the receptionist cashed up twice, or the
 * consultation was closed by hand first — must not turn a successful payment
 * into an error page, so the failure is swallowed here.
 */
final readonly class MessengerAdmissionCoordinator implements AdmissionCoordinatorInterface
{
    private const string RELEASED_TO_OWNER = 'released_to_owner';

    public function __construct(private CommandBusInterface $commandBus)
    {
    }

    public function closeAfterCheckout(string $admissionId, string $clinicId): void
    {
        try {
            $this->commandBus->dispatch(new CloseAdmission(
                clinicId: $clinicId,
                admissionId: $admissionId,
                closureReason: self::RELEASED_TO_OWNER,
            ));
        } catch (\DomainException) {
            // Already closed, or gone: the money is in either way.
        }
    }
}
