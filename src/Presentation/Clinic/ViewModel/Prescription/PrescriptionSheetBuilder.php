<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Prescription;

use App\Context\Consultation\Application\Query\GetConsultationDetails\ConsultationDetailsDTO;
use App\Context\Consultation\Application\Query\GetConsultationDetails\GetConsultationDetails;
use App\Context\Prescription\Application\Query\GetPrescriptionDetails\GetPrescriptionDetails;
use App\Context\Prescription\Application\Query\GetPrescriptionDetails\PrescriptionDetails;
use App\Context\Prescription\Application\Query\GetPrescriptionDetails\PrescriptionLineView;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * The side sheet of one ordonnance (ordonnances-v-topbar_3 « Fiche »): its
 * header, the prescriber / issue / validity strip, the consultation it comes
 * from, its medicines, the paper it prints as, its history, and the acts
 * still open on it — signing a draft, renewing or cancelling a signed one —
 * each with the form target and CSRF token its single-action controller wants.
 */
final readonly class PrescriptionSheetBuilder
{
    private const array SPECIES = ['dog' => 'chien', 'cat' => 'chat', 'nac' => 'NAC', 'other' => 'animal'];

    /** Line class → the « .puce cl-* » of the mock-up. */
    private const array CLASS_CHIPS = ['NONE' => 'cl-libre', 'RX' => 'cl-l1', 'RX_CONTROLLED' => 'cl-l1', 'RX_NARCOTIC' => 'cl-stup'];

    /** Renewals a draft may be given when it is signed (0 to 6, as before). */
    private const int MAX_RENEWALS = 6;

    public function __construct(
        private QueryBusInterface $queryBus,
        private UrlGeneratorInterface $urls,
        private CsrfTokenManagerInterface $csrf,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @param array<string, array{id: string, n: string, ini: string, c: string}> $team
     *
     * @return array<string, mixed>|null null when the ordonnance is not this clinic's
     */
    public function build(string $clinicId, string $prescriptionId, array $team, \DateTimeZone $tz): ?array
    {
        try {
            $p = $this->queryBus->ask(new GetPrescriptionDetails($clinicId, $prescriptionId));
        } catch (\LogicException) { // unknown id (DomainException) or malformed one (InvalidArgumentException)
            return null;
        }

        if (!$p instanceof PrescriptionDetails) {
            return null;
        }

        $now     = $this->clock->now();
        $issued  = ($p->issuedAt ?? $p->createdAt)->setTimezone($tz);
        $status  = PrescriptionRegisterViewBuilder::status($p->status, $p->isExpired);
        $vet     = $team[$p->prescriberId] ?? null;
        $daysOut = null !== $p->validUntil ? (int) floor(($p->validUntil->getTimestamp() - $now->getTimestamp()) / 86400) : null;

        return [
            'id'    => $p->id,
            'num'   => $p->number,
            'st'    => $status,
            'd'     => $issued->format('Y-m-d'),
            'h'     => $issued->format('H:i'),
            'valid' => $p->validUntil?->setTimezone($tz)->format('Y-m-d'),
            'jours' => $daysOut,
            'a'     => [
                'n'    => $p->patientName,
                'e'    => PrescriptionRegisterViewBuilder::emoji($p->patientSpecies),
                'd'    => $this->animalLine($p),
                'puce' => $p->patientMicrochipNumber,
                'prop' => $p->ownerName,
                'adr'  => $p->ownerAddressLines,
            ],
            'vet' => [
                'n'   => $p->prescriberName,
                'ini' => $vet['ini'] ?? PrescriptionRegisterViewBuilder::initials($p->prescriberName),
                'c'   => $vet['c'] ?? '#64748b',
                'ord' => $p->prescriberRegistrationNumber,
            ],
            'cons'   => $this->consultation($clinicId, $p, $tz),
            'lignes' => array_map(fn (PrescriptionLineView $line): array => $this->line($line), $p->lines),
            'flags'  => PrescriptionRegisterViewBuilder::flags(array_map(
                static fn (PrescriptionLineView $line): string => $line->prescriptionClass,
                $p->lines,
            )),
            'note'   => $p->notes,
            'renouv' => ['allowed' => $p->renewalsAllowed, 'used' => $p->renewalsUsed, 'remaining' => $p->renewalsRemaining],
            'annul'  => 'CANCELLED' === $p->status ? [
                'd'     => ($p->cancelledAt ?? $now)->setTimezone($tz)->format('Y-m-d'),
                'motif' => $p->cancellationReason ?? 'Annulée',
            ] : null,
            'hist'    => $this->history($p, $tz),
            'pdf'     => $this->urls->generate('clinic_prescription_pdf', ['prescriptionId' => $p->id]),
            'actions' => $this->actions($p, $now),
        ];
    }

    /**
     * « chien Labrador · 7 ans · 31 kg », as the paper prints the animal.
     */
    private function animalLine(PrescriptionDetails $p): string
    {
        $parts = [trim((self::SPECIES[$p->patientSpecies ?? ''] ?? '') . ' ' . ($p->patientBreed ?? ''))];

        if (null !== $p->patientBirthDate) {
            $years   = $p->patientBirthDate->diff($this->clock->now())->y;
            $parts[] = $years > 0 ? \sprintf('%d an%s', $years, $years > 1 ? 's' : '') : 'moins d\'un an';
        }

        if (null !== $p->patientWeightKg) {
            $parts[] = str_replace('.', ',', $p->patientWeightKg) . ' kg';
        }

        return implode(' · ', array_filter($parts, static fn (string $part): bool => '' !== $part));
    }

    /**
     * @return array{label: string, d: string, url: string}
     */
    private function consultation(string $clinicId, PrescriptionDetails $p, \DateTimeZone $tz): array
    {
        $label = 'Consultation';
        $at    = $p->createdAt->setTimezone($tz);

        try {
            $consultation = $this->queryBus->ask(new GetConsultationDetails($p->consultationId, $clinicId));
        } catch (\LogicException) { // unknown id (DomainException) or malformed one (InvalidArgumentException)
            $consultation = null;
        }

        if ($consultation instanceof ConsultationDetailsDTO) {
            $motifs = array_map(static fn (array $motif): string => $motif['label'], $consultation->motifs);
            $label  = [] !== $motifs ? implode(' · ', $motifs) : ($consultation->chiefComplaint ?? $label);
            $at     = new \DateTimeImmutable($consultation->startedAtUtc, new \DateTimeZone('UTC'))->setTimezone($tz);
        }

        return [
            'label' => $label,
            'd'     => $at->format('Y-m-d'),
            'url'   => $this->urls->generate('clinic_consultation_details', ['id' => $p->consultationId]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function line(PrescriptionLineView $line): array
    {
        $quantity = rtrim(rtrim($line->quantity, '0'), '.');

        return [
            'l'       => $line->label,
            'form'    => $line->pharmaceuticalForm,
            'subst'   => $line->activeSubstances,
            'poso'    => $line->posology,
            'instr'   => $line->instructions,
            'q'       => str_replace('.', ',', '' !== $quantity ? $quantity : '0'),
            'cl'      => self::CLASS_CHIPS[$line->prescriptionClass] ?? 'cl-libre',
            'clLabel' => $line->prescriptionClassLabel,
            'ctrl'    => $line->isControlled,
            // A line taken from the catalogue was delivered by the clinic during
            // the consultation; a free-text one is bought in a pharmacy.
            'clinique' => null !== $line->articleId,
        ];
    }

    /**
     * What happened to the document, oldest first.
     *
     * @return list<array{d: string, t: string, s: string}>
     */
    private function history(PrescriptionDetails $p, \DateTimeZone $tz): array
    {
        $day  = static fn (\DateTimeImmutable $at): string => $at->setTimezone($tz)->format('Y-m-d');
        $hist = [['d' => $day($p->createdAt), 't' => 'Brouillon créé', 's' => 'Depuis le plan de la consultation']];

        if (null !== $p->issuedAt) {
            $hist[] = ['d' => $day($p->issuedAt), 't' => 'Émise et signée', 's' => $p->prescriberName . ($p->prescriberRegistrationNumber ? ' · n° ordinal ' . $p->prescriberRegistrationNumber : '')];
        }

        foreach ($p->renewals as $renewal) {
            $hist[] = ['d' => $day($renewal->renewedAt), 't' => 'Renouvelée', 's' => 'Valable jusqu\'au ' . $renewal->newValidUntil->setTimezone($tz)->format('d/m/Y')];
        }

        if (null !== $p->cancelledAt) {
            $hist[] = ['d' => $day($p->cancelledAt), 't' => 'Annulée', 's' => $p->cancellationReason ?? ''];
        }

        return $hist;
    }

    /**
     * The acts still open on the document, each with its form target.
     *
     * @return array<string, mixed>
     */
    private function actions(PrescriptionDetails $p, \DateTimeImmutable $now): array
    {
        $form = fn (string $route, string $intent): array => [
            'url'   => $this->urls->generate($route, ['prescriptionId' => $p->id]),
            'token' => $this->csrf->getToken($intent . $p->id)->getValue(),
        ];

        $actions = ['consultation' => $this->urls->generate('clinic_consultation_details', ['id' => $p->consultationId])];

        if ('DRAFT' === $p->status) {
            $actions['issue'] = $form('clinic_prescription_issue', 'prescription_issue_') + [
                'canSign'      => null !== $p->prescriberRegistrationNumber,
                'hasLines'     => [] !== $p->lines,
                'maxRenewals'  => $p->carriesControlledSubstance ? 0 : self::MAX_RENEWALS,
                'validityDays' => $p->validityDays,
                'controlled'   => $p->carriesControlledSubstance,
            ];
        }

        if ('ISSUED' === $p->status) {
            $actions['cancel'] = $form('clinic_prescription_cancel', 'prescription_cancel_');

            if (!$p->isExpired && !$p->carriesControlledSubstance && $p->renewalsRemaining > 0 && null !== $p->validUntil && $p->validUntil > $now) {
                $actions['renew'] = $form('clinic_prescription_renew', 'prescription_renew_') + [
                    'validityDays' => $p->validityDays,
                ];
            }
        }

        return $actions;
    }
}
