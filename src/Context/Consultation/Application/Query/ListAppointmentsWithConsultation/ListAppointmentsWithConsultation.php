<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Query\ListAppointmentsWithConsultation;

use App\Shared\Application\Bus\QueryInterface;

/**
 * Which of the listed appointments a consultation was started from (the
 * Portal freezes the pre-visit questionnaire of such an appointment).
 * Answers `list<string>` of appointment ids.
 */
final readonly class ListAppointmentsWithConsultation implements QueryInterface
{
    /**
     * @param list<string> $appointmentIds
     */
    public function __construct(
        public string $clinicId,
        public array $appointmentIds,
    ) {
    }
}
