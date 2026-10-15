<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Dashboard;

/**
 * What the dashboard's quick look (« Aperçu rapide ») tells about a patient
 * without leaving the page: the owner to call, the vigilance points, the last
 * weight and the last visit.
 */
final readonly class PatientFacts
{
    /**
     * @param list<array{label: string, tone: 'a'|'r'}> $vigilance amber for behaviour and treatments, red otherwise
     */
    public function __construct(
        public ?string $owner,
        public ?string $phone,
        public ?string $age,
        public array $vigilance,
        public ?string $weight,
        public string $lastVisit,
        public ?string $todayConsultationId,
    ) {
    }

    /** The phone number as a `tel:` target. */
    public function dialable(): ?string
    {
        return null === $this->phone ? null : str_replace(' ', '', $this->phone);
    }
}
