<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Prescription;

use App\Presentation\Clinic\ViewModel\Consultation\CockpitContextBuilder;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Everything the « Ordonnances » page module starts from: the register's
 * first page, the filters, the prescribers, the letterhead of the paper
 * preview, the day's consultations and — on the details route — the sheet of
 * the ordonnance to open straight away.
 */
final readonly class PrescriptionPageBuilder
{
    public function __construct(
        private PrescriptionRegisterViewBuilder $register,
        private PrescriptionSheetBuilder $sheets,
        private CockpitContextBuilder $context,
        private UrlGeneratorInterface $urls,
    ) {
    }

    public function timeZone(string $clinicId): \DateTimeZone
    {
        return new \DateTimeZone($this->context->letterhead($clinicId)['timeZone']);
    }

    /**
     * @return array<string, mixed>|null null when the ordonnance asked for is not this clinic's
     */
    public function sheet(string $clinicId, string $prescriptionId): ?array
    {
        return $this->sheets->build($clinicId, $prescriptionId, $this->register->team($clinicId), $this->timeZone($clinicId));
    }

    /**
     * @param array<string, mixed>|null $sheet
     *
     * @return array<string, mixed>
     */
    public function page(string $clinicId, PrescriptionRegisterFilters $filters, ?array $sheet = null): array
    {
        $letterhead = $this->context->letterhead($clinicId);
        $tz         = new \DateTimeZone($letterhead['timeZone']);

        return [
            'today'         => new \DateTimeImmutable('now', $tz)->format('Y-m-d'),
            'filters'       => $filters->toArray(),
            'register'      => $this->register->register($clinicId, $filters, $tz),
            'team'          => array_values($this->register->team($clinicId)),
            'letterhead'    => $letterhead,
            'consultations' => $this->register->consultationsToday($clinicId, $tz),
            'sheet'         => $sheet,
            'urls'          => [
                'list'      => $this->urls->generate('clinic_prescriptions'),
                'search'    => $this->urls->generate('clinic_prescriptions_search'),
                'sheet'     => $this->urls->generate('clinic_prescription_sheet', ['prescriptionId' => '__ID__']),
                'details'   => $this->urls->generate('clinic_prescription_details', ['prescriptionId' => '00000000-0000-7000-8000-000000000000']),
                'selection' => $this->urls->generate('clinic_prescriptions_selection_pdf'),
                'export'    => $this->urls->generate('clinic_prescriptions_export'),
            ],
        ];
    }
}
