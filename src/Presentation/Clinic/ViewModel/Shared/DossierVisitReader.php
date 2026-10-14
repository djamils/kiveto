<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Shared;

use App\Context\Billing\Application\Query\SearchInvoices\InvoiceListRow;
use App\Context\Billing\Domain\ValueObject\PaymentMethod;
use App\Context\Clinic\Application\Query\Staff\ListClinicVeterinarians\ClinicVeterinarianItem;
use App\Context\Clinic\Application\Query\Staff\ListClinicVeterinarians\ListClinicVeterinarians;
use App\Context\Consultation\Domain\ValueObject\VitalType;
use App\Shared\Application\Bus\QueryBusInterface;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * The visits the client and animal sheets share (« Visites du foyer » of the
 * dossier mock-ups): a consultation with its motifs, diagnosis and vitals, its
 * ordonnances, its invoice with its lines and payment, all in the shape the
 * dossier page modules render.
 *
 * The consultations come from the sheet, which knows whose they are; each fact
 * no query exposes yet is read here in one batched statement.
 */
final readonly class DossierVisitReader
{
    /** Motif words that file a visit under a vaccination or a hospitalisation. */
    private const array VACCINATION_WORDS     = ['vaccin', 'rappel', 'primo'];
    private const array HOSPITALISATION_WORDS = ['chirurg', 'hospitalis', 'dentaire', 'détartrage', 'anesthés', 'castration', 'stérilisation'];
    private const array EXAM_WORDS            = ['imagerie', 'analyse', 'bilan sanguin', 'radiograph', 'échograph'];

    public function __construct(
        private QueryBusInterface $queryBus,
        private Connection $connection,
        private UrlGeneratorInterface $urls,
        private CsrfTokenManagerInterface $csrf,
    ) {
    }

    /**
     * @return array<string, string> user id => display name
     */
    public function practitioners(string $clinicId): array
    {
        $veterinarians = $this->queryBus->ask(new ListClinicVeterinarians($clinicId));
        \assert(\is_array($veterinarians));

        $names = [];

        foreach ($veterinarians as $vet) {
            \assert($vet instanceof ClinicVeterinarianItem);

            if (null !== $vet->displayName) {
                $names[$vet->userId] = $vet->displayName;
            }
        }

        return $names;
    }

    /**
     * @param list<InvoiceListRow> $invoices
     *
     * @return array<string, list<array{0: string, 1: float, 2: float}>> invoice id => [label, quantity, gross]
     */
    public function invoiceLines(array $invoices): array
    {
        if ([] === $invoices) {
            return [];
        }

        $rows = $this->connection->fetchAllAssociative(
            'SELECT BIN_TO_UUID(invoice_id) AS invoice_id, label, quantity, gross_minor_units
             FROM billing__invoice_lines WHERE invoice_id IN (?) ORDER BY position ASC',
            [array_map(static fn (InvoiceListRow $i): string => Uuid::fromString($i->id)->toBinary(), $invoices)],
            [ArrayParameterType::BINARY],
        );

        $lines = [];

        foreach ($rows as $row) {
            \assert(\is_string($row['invoice_id']) && \is_string($row['label']));

            $quantity = is_numeric($row['quantity']) ? (float) $row['quantity'] : 1.0;
            $gross    = is_numeric($row['gross_minor_units']) ? (int) $row['gross_minor_units'] : 0;

            $lines[$row['invoice_id']][] = [$row['label'], $quantity, $gross / 100];
        }

        return $lines;
    }

    /**
     * @param list<InvoiceListRow> $invoices
     *
     * @return array<string, string> invoice id => label of the latest payment method
     */
    public function lastPaymentMethods(array $invoices): array
    {
        if ([] === $invoices) {
            return [];
        }

        $rows = $this->connection->fetchAllAssociative(
            'SELECT BIN_TO_UUID(invoice_id) AS invoice_id, method
             FROM billing__payments WHERE invoice_id IN (?) ORDER BY received_at ASC, position ASC',
            [array_map(static fn (InvoiceListRow $i): string => Uuid::fromString($i->id)->toBinary(), $invoices)],
            [ArrayParameterType::BINARY],
        );

        $methods = [];

        foreach ($rows as $row) {
            \assert(\is_string($row['invoice_id']) && \is_string($row['method']));

            // Ordered oldest first: the last one written wins.
            $methods[$row['invoice_id']] = PaymentMethod::tryFrom($row['method'])?->label() ?? $row['method'];
        }

        return $methods;
    }

    /**
     * Issued prescriptions of the listed consultations, with their lines.
     *
     * @param list<array{id: string, ...}> $consultations
     *
     * @return array<string, list<array{
     *     id: string,
     *     number: string,
     *     issuedAt: \DateTimeImmutable,
     *     prescriber: ?string,
     *     renewable: bool,
     *     lines: list<array{0: string, 1: string, 2: string}>
     * }>> consultation id => prescriptions
     */
    public function prescriptions(array $consultations): array
    {
        if ([] === $consultations) {
            return [];
        }

        $rows = $this->connection->fetchAllAssociative(
            "SELECT BIN_TO_UUID(pr.id) AS id, BIN_TO_UUID(pr.consultation_id) AS consultation_id,
                    pr.number, pr.issued_at, pr.prescriber_display_name, pr.renewals_allowed, pr.renewals_used
             FROM prescription__prescriptions pr
             WHERE pr.consultation_id IN (?) AND pr.status = 'ISSUED' AND pr.number IS NOT NULL
             ORDER BY pr.issued_at DESC",
            [array_map(static fn (array $c): string => Uuid::fromString($c['id'])->toBinary(), $consultations)],
            [ArrayParameterType::BINARY],
        );

        if ([] === $rows) {
            return [];
        }

        $lines = [];

        $lineRows = $this->connection->fetchAllAssociative(
            'SELECT BIN_TO_UUID(prescription_id) AS prescription_id, label, dose, frequency, duration_days, route, quantity
             FROM prescription__prescription_lines WHERE prescription_id IN (?) ORDER BY position ASC',
            [array_map(static function (array $row): string {
                \assert(\is_string($row['id']));

                return Uuid::fromString($row['id'])->toBinary();
            }, $rows)],
            [ArrayParameterType::BINARY],
        );

        foreach ($lineRows as $line) {
            \assert(\is_string($line['prescription_id']) && \is_string($line['label']));

            $posology = implode(', ', array_filter([
                $this->text($line['dose']),
                $this->text($line['frequency']),
                is_numeric($line['duration_days']) ? \sprintf('%d j', (int) $line['duration_days']) : null,
                $this->text($line['route']),
            ]));
            $quantity = is_numeric($line['quantity']) ? $this->number((float) $line['quantity']) : '—';

            $lines[$line['prescription_id']][] = [$line['label'], '' === $posology ? '—' : $posology, $quantity];
        }

        $prescriptions = [];

        foreach ($rows as $row) {
            \assert(\is_string($row['id']) && \is_string($row['consultation_id']));
            \assert(\is_string($row['number']) && \is_string($row['issued_at']));

            $allowed = is_numeric($row['renewals_allowed']) ? (int) $row['renewals_allowed'] : 0;
            $used    = is_numeric($row['renewals_used']) ? (int) $row['renewals_used'] : 0;

            $prescriptions[$row['consultation_id']][] = [
                'id'         => $row['id'],
                'number'     => $row['number'],
                'issuedAt'   => new \DateTimeImmutable($row['issued_at']),
                'prescriber' => $this->text($row['prescriber_display_name']),
                'renewable'  => $allowed > $used,
                'lines'      => $lines[$row['id']] ?? [],
            ];
        }

        return $prescriptions;
    }

    /**
     * Motifs, diagnoses and typed vitals of the listed consultations.
     *
     * @param list<array{id: string, ...}> $consultations
     *
     * @return array<string, array{motifs: list<string>, diagnosis: ?string, vitals: list<array{0: string, 1: string}>}>
     */
    public function consultationDetails(array $consultations): array
    {
        if ([] === $consultations) {
            return [];
        }

        $ids       = [array_map(static fn (array $c): string => Uuid::fromString($c['id'])->toBinary(), $consultations)];
        $types     = [ArrayParameterType::BINARY];
        $motifList = [];
        $diagnosis = [];
        $vitalList = [];

        $motifs = $this->connection->fetchAllAssociative(
            'SELECT BIN_TO_UUID(consultation_id) AS consultation_id, label FROM consultation__motifs
             WHERE consultation_id IN (?) ORDER BY position ASC',
            $ids,
            $types,
        );

        foreach ($motifs as $row) {
            \assert(\is_string($row['consultation_id']) && \is_string($row['label']));
            $motifList[$row['consultation_id']][] = $row['label'];
        }

        $diagnoses = $this->connection->fetchAllAssociative(
            'SELECT BIN_TO_UUID(consultation_id) AS consultation_id, label FROM consultation__diagnoses
             WHERE consultation_id IN (?) ORDER BY is_primary DESC, position ASC',
            $ids,
            $types,
        );

        foreach ($diagnoses as $row) {
            \assert(\is_string($row['consultation_id']) && \is_string($row['label']));
            // The primary diagnosis comes first.
            $diagnosis[$row['consultation_id']] ??= $row['label'];
        }

        $vitals = $this->connection->fetchAllAssociative(
            'SELECT BIN_TO_UUID(consultation_id) AS consultation_id, type, value FROM consultation__typed_vitals
             WHERE consultation_id IN (?) ORDER BY position ASC',
            $ids,
            $types,
        );

        foreach ($vitals as $row) {
            \assert(\is_string($row['consultation_id']) && \is_string($row['type']));

            $type = VitalType::tryFrom($row['type']);

            if (null === $type || !\is_scalar($row['value'])) {
                continue;
            }

            $value = is_numeric($row['value']) ? $this->number((float) $row['value']) : (string) $row['value'];

            $vitalList[$row['consultation_id']][] = [$type->label(), trim($value . ' ' . $type->unit())];
        }

        $details = [];

        foreach ($consultations as $consultation) {
            $details[$consultation['id']] = [
                'motifs'    => $motifList[$consultation['id']] ?? [],
                'diagnosis' => $diagnosis[$consultation['id']] ?? null,
                'vitals'    => $vitalList[$consultation['id']] ?? [],
            ];
        }

        return $details;
    }

    /**
     * @param array<string, list<array{id: string, number: string, issuedAt: \DateTimeImmutable, prescriber: ?string, renewable: bool, lines: list<array{0: string, 1: string, 2: string}>}>> $prescriptions
     *
     * @return array<string, array<string, mixed>> prescription id => what the preview window shows
     */
    public function ordosView(array $prescriptions, \DateTimeZone $tz): array
    {
        $ordos = [];

        foreach ($prescriptions as $list) {
            foreach ($list as $prescription) {
                $ordos[$prescription['id']] = [
                    'num'    => $prescription['number'],
                    'd'      => $prescription['issuedAt']->setTimezone($tz)->format('Y-m-d'),
                    'v'      => $prescription['prescriber'] ?? '—',
                    'lignes' => $prescription['lines'],
                    'href'   => $this->urls->generate('clinic_prescription_details', ['prescriptionId' => $prescription['id']]),
                    'pdf'    => $this->urls->generate('clinic_prescription_pdf', ['prescriptionId' => $prescription['id']]),
                    'renew'  => $prescription['renewable'] ? [
                        'url'   => $this->urls->generate('clinic_prescription_renew', ['prescriptionId' => $prescription['id']]),
                        'token' => $this->csrf->getToken('prescription_renew_' . $prescription['id'])->getValue(),
                    ] : null,
                ];
            }
        }

        return $ordos;
    }

    /**
     * One history row per consultation: its synthesis, its documents and its invoice.
     *
     * @param list<array{id: string, admissionId: ?string, practitionerUserId: string, status: string, startedAt: \DateTimeImmutable, reason: ?string, summary: ?string, observations: ?string, weightKg: ?string, temperature: ?string, animalId: string, animalName: string}> $consultations
     * @param list<InvoiceListRow>                                                                                                                                                                                                                                              $invoices
     * @param array<string, list<array{0: string, 1: float, 2: float}>>                                                                                                                                                                                                         $lines
     * @param array<string, string>                                                                                                                                                                                                                                             $payments
     * @param array<string, list<array{id: string, number: string, ...}>>                                                                                                                                                                                                       $prescriptions
     * @param array<string, array{motifs: list<string>, diagnosis: ?string, vitals: list<array{0: string, 1: string}>}>                                                                                                                                                         $details
     * @param array<string, string>                                                                                                                                                                                                                                             $practitioners
     *
     * @return list<array<string, mixed>>
     */
    public function consultationVisits(
        array $consultations,
        array $invoices,
        array $lines,
        array $payments,
        array $prescriptions,
        array $details,
        array $practitioners,
        \DateTimeZone $tz,
    ): array {
        $invoiceByAdmission = [];

        foreach ($invoices as $invoice) {
            // Newest first: the latest invoice of a visit wins.
            if (null !== $invoice->admissionId) {
                $invoiceByAdmission[$invoice->admissionId] ??= $invoice;
            }
        }

        $visits = [];

        foreach ($consultations as $consultation) {
            $startedAt = $consultation['startedAt']->setTimezone($tz);
            $detail    = $details[$consultation['id']] ?? ['motifs' => [], 'diagnosis' => null, 'vitals' => []];
            $open      = 'OPEN' === $consultation['status'];
            $invoice   = null === $consultation['admissionId'] ? null : $invoiceByAdmission[$consultation['admissionId']] ?? null;

            $syn = array_filter([
                'Motif'      => $consultation['reason'],
                'Examen'     => $consultation['observations'],
                'Diagnostic' => $detail['diagnosis'],
                'Conclusion' => $consultation['summary'],
            ], static fn (?string $value): bool => null !== $value);

            $mes = [];

            if (null !== $consultation['weightKg']) {
                $mes[] = ['Poids', $this->number((float) $consultation['weightKg']) . ' kg'];
            }

            if (null !== $consultation['temperature']) {
                $mes[] = ['Température', $this->number((float) $consultation['temperature']) . ' °C'];
            }

            $docs = [];

            if (!$open) {
                $docs[] = [
                    'Compte rendu de consultation',
                    'doc',
                    $this->urls->generate('clinic_consultation_report_pdf', ['consultationId' => $consultation['id']]),
                ];
            }

            $visits[] = [
                'id'     => 'c-' . $consultation['id'],
                'd'      => $startedAt->format('Y-m-d'),
                'h'      => $startedAt->format('H:i'),
                'a'      => $consultation['animalId'],
                'k'      => $this->visitKind($detail['motifs'], $consultation['reason']),
                't'      => $this->visitTitle($detail['motifs'], $consultation['reason']),
                'v'      => $practitioners[$consultation['practitionerUserId']] ?? '—',
                'st'     => $open ? 'encours' : 'venu',
                'syn'    => [] === $syn ? null : $syn,
                'mes'    => [...$mes, ...$detail['vitals']],
                'lignes' => null === $invoice ? null : $lines[$invoice->id] ?? [],
                'total'  => null === $invoice ? 0 : $invoice->totalGrossMinorUnits / 100,
                'fac'    => null === $invoice ? null : $this->facView($invoice, $payments, $tz),
                'docs'   => $docs,
                'ordos'  => array_map(static fn (array $p): string => $p['id'], $prescriptions[$consultation['id']] ?? []),
                'href'   => $this->urls->generate('clinic_consultation_details', ['id' => $consultation['id']]),
                'report' => $open ? null : $this->urls->generate('clinic_consultation_report_pdf', ['consultationId' => $consultation['id']]),
            ];
        }

        return $visits;
    }

    /**
     * @param array<string, string> $payments
     *
     * @return array{num: string, st: string, p: string, invoiceId: string, balance: float, balanceInput: string, href: string}
     */
    public function facView(InvoiceListRow $invoice, array $payments, \DateTimeZone $tz): array
    {
        $issued = null !== $invoice->number && null !== $invoice->issuedAt;
        $due    = $issued && $invoice->balanceDueMinorUnits > 0;

        $state = match (true) {
            !$issued => 'cours',
            $due     => 'du',
            default  => 'ok',
        };

        $detail = match ($state) {
            'cours' => 'À encaisser en fin de consultation',
            'du'    => $invoice->amountPaidMinorUnits > 0
                ? \sprintf('Réglée en partie · reste %s', SheetText::money($invoice->balanceDueMinorUnits, $invoice->currency))
                : \sprintf('Émise le %s', SheetText::date(($invoice->issuedAt ?? $invoice->createdAt)->setTimezone($tz), 'd MMM')),
            default => $payments[$invoice->id] ?? 'Réglée',
        };

        return [
            'num'          => $invoice->number ?? 'Brouillon',
            'st'           => $state,
            'p'            => $detail,
            'invoiceId'    => $invoice->id,
            'balance'      => $invoice->balanceDueMinorUnits / 100,
            'balanceInput' => number_format($invoice->balanceDueMinorUnits / 100, 2, ',', ''),
            'href'         => $this->urls->generate('clinic_invoice_details', ['invoiceId' => $invoice->id]),
        ];
    }

    /**
     * @param list<string> $motifs
     */
    public function visitKind(array $motifs, ?string $reason): string
    {
        $text = mb_strtolower(implode(' ', $motifs) . ' ' . ($reason ?? ''));

        $kinds = [
            'vacc' => self::VACCINATION_WORDS,
            'hosp' => self::HOSPITALISATION_WORDS,
            'labo' => self::EXAM_WORDS,
        ];

        foreach ($kinds as $kind => $words) {
            foreach ($words as $word) {
                if (str_contains($text, $word)) {
                    return $kind;
                }
            }
        }

        return 'cons';
    }

    /**
     * The motifs read as a short title (« Vaccination et bilan annuel »); the
     * chief complaint stands in when none was picked.
     *
     * @param list<string> $motifs
     */
    public function visitTitle(array $motifs, ?string $reason): string
    {
        if ([] !== $motifs) {
            $rest = array_map(static fn (string $m): string => mb_strtolower(mb_substr($m, 0, 1)) . mb_substr($m, 1), \array_slice($motifs, 1));

            return implode(' et ', [$motifs[0], ...$rest]);
        }

        return null === $reason ? 'Consultation' : rtrim($reason, '. ');
    }

    public function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, ',', ' '), '0'), ',');
    }

    private function text(mixed $value): ?string
    {
        return \is_string($value) && '' !== trim($value) ? trim($value) : null;
    }
}
