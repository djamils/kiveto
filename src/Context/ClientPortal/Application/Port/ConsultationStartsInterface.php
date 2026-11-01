<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

/** Which appointments a consultation was started from (Consultation context). */
interface ConsultationStartsInterface
{
    /**
     * @param list<string> $appointmentIds
     *
     * @return list<string> the appointments of the list a consultation of the clinic was started from
     */
    public function startedFrom(string $clinicId, array $appointmentIds): array;
}
