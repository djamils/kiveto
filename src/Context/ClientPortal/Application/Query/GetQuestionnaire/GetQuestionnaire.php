<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\GetQuestionnaire;

use App\Shared\Application\Bus\QueryInterface;

/**
 * The pre-visit questionnaire of an appointment. With a `userId` (Portal):
 * only an appointment of the account, neither cancelled nor declined
 * (`AppointmentNotFoundException` otherwise). Without (VetApp agenda and
 * cockpit): any appointment of the clinic. Answers a PortalQuestionnaire
 * (`not_started` when none was written).
 */
final readonly class GetQuestionnaire implements QueryInterface
{
    public function __construct(
        public string $clinicId,
        public string $appointmentId,
        public ?string $userId = null,
    ) {
    }
}
