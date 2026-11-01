<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\Exception;

/**
 * A client may no longer cancel or move the appointment online: the
 * clinic's cancellation window is closed (`now > cancellableUntil`), or the
 * appointment has started. The API answers 422 `cancellation_window_closed`.
 */
final class CancellationWindowClosedException extends \DomainException
{
    public function __construct(
        public readonly string $appointmentId,
        public readonly ?\DateTimeImmutable $cancellableUntil,
    ) {
        parent::__construct('Ce rendez-vous ne peut plus être annulé ni déplacé en ligne : contactez la clinique.');
    }
}
