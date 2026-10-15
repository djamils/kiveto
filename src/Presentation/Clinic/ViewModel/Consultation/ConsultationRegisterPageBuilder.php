<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Consultation;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Everything the « Consultations » page module starts from: the register's
 * first page and counters, the filters, the practitioners, the waiting room
 * of the « Nouvelle consultation » window and — when the address names one —
 * the sheet of the consultation to open straight away.
 */
final readonly class ConsultationRegisterPageBuilder
{
    public function __construct(
        private ConsultationRegisterViewBuilder $register,
        private ConsultationRegisterSheetBuilder $sheets,
        private CockpitContextBuilder $context,
        private UrlGeneratorInterface $urls,
        private CsrfTokenManagerInterface $csrf,
    ) {
    }

    public function timeZone(string $clinicId): \DateTimeZone
    {
        return new \DateTimeZone($this->context->letterhead($clinicId)['timeZone']);
    }

    /**
     * @return ?array<string, mixed> null when the consultation is not this clinic's
     */
    public function sheet(string $clinicId, string $consultationId): ?array
    {
        return $this->sheets->build($clinicId, $consultationId, $this->timeZone($clinicId));
    }

    /**
     * @param ?array<string, mixed> $sheet
     *
     * @return array<string, mixed>
     */
    public function page(string $clinicId, ConsultationRegisterFilters $filters, ?array $sheet = null): array
    {
        $tz = $this->timeZone($clinicId);

        return [
            'today'    => $this->register->today($tz)->format('Y-m-d'),
            'filters'  => $filters->toArray(),
            'register' => $this->register->register($clinicId, $filters, $tz),
            'team'     => array_values($this->register->team($clinicId)),
            'waiting'  => $this->register->waitingRoom($clinicId),
            'sheet'    => $sheet,
            'token'    => $this->csrf->getToken('consultation_start')->getValue(),
            'urls'     => [
                'list'      => $this->urls->generate('clinic_consultations'),
                'search'    => $this->urls->generate('clinic_consultations_search'),
                'sheet'     => $this->urls->generate('clinic_consultation_sheet', ['consultationId' => '00000000-0000-7000-8000-000000000000']),
                'selection' => $this->urls->generate('clinic_consultations_reports_pdf'),
                'patients'  => $this->urls->generate('clinic_search', ['format' => 'consultations', 'type' => 'animal']),
                'flux'      => $this->urls->generate('clinic_admission_queue'),
            ],
        ];
    }
}
