<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Billing;

use App\Context\Billing\Application\Query\GetCreditNoteDetails\CreditNoteDetails;
use App\Context\Billing\Application\Query\GetCreditNoteDetails\GetCreditNoteDetails;
use App\Context\Billing\Application\Query\GetInvoiceDetails\CreditNoteSummaryView;
use App\Context\Billing\Application\Query\GetInvoiceDetails\GetInvoiceDetails;
use App\Context\Billing\Application\Query\GetInvoiceDetails\InvoiceDetails;
use App\Context\Billing\Application\Query\GetInvoiceDetails\InvoiceLineView;
use App\Context\Billing\Application\Query\GetInvoiceDetails\PaymentView;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Presentation\Twig\PhoneFormatRuntime;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * The side sheet of one invoice or credit note (facturation-v-topbar_3
 * « Fiche »): what is owed or settled, the visit it charges, its lines and
 * VAT, its payments, the paper it prints as, its history — and the acts still
 * open on it (take a payment, cash a visit up, issue a credit note), each with
 * the form target and CSRF token its single-action controller wants.
 */
final readonly class BillingSheetBuilder
{
    /** Payment method → the mock-up's mode key. */
    public const array MODES = ['CARD' => 'cb', 'CASH' => 'esp', 'CHECK' => 'chq', 'TRANSFER' => 'vir', 'INSURANCE' => 'ass', 'OTHER' => 'autre'];

    public function __construct(
        private QueryBusInterface $queryBus,
        private BillingVisitReader $visits,
        private UrlGeneratorInterface $urls,
        private CsrfTokenManagerInterface $csrf,
        private PhoneFormatRuntime $phones,
    ) {
    }

    /**
     * @param array<string, array{id: string, n: string, ini: string, c: string}> $team
     *
     * @return array<string, mixed>|null null when the invoice is not this clinic's
     */
    public function invoice(string $clinicId, string $invoiceId, array $team, \DateTimeZone $tz): ?array
    {
        try {
            $invoice = $this->queryBus->ask(new GetInvoiceDetails($clinicId, $invoiceId));
        } catch (\LogicException) { // unknown id (DomainException) or malformed one (InvalidArgumentException)
            return null;
        }

        if (!$invoice instanceof InvoiceDetails) {
            return null;
        }

        $visit    = $this->visit($invoice->admissionId);
        $status   = BillingRegisterViewBuilder::status($invoice->status);
        $at       = ($invoice->issuedAt ?? $invoice->createdAt)->setTimezone($tz);
        $credited = [];

        foreach ($invoice->creditNotes as $note) {
            $credited = [...$credited, ...$note->creditedLineIds];
        }

        $paid = $invoice->amountPaidMinorUnits - $invoice->refundedMinorUnits;

        return [
            'id'     => $invoice->id,
            'kind'   => 'fac',
            'num'    => $invoice->number,
            'st'     => $status,
            'raw'    => $invoice->status,
            'd'      => $at->format('Y-m-d'),
            'h'      => $at->format('H:i'),
            'cli'    => $this->client($invoice->payerClientId, $invoice->payerName, $invoice->payerAddressLines, $invoice->payerAttention),
            'vet'    => null !== $visit && null !== $visit['vetId'] ? ($team[$visit['vetId']] ?? null) : null,
            'anis'   => null !== $visit ? [BillingRegisterViewBuilder::animal($visit['animal'])] : [],
            'objet'  => BillingRegisterViewBuilder::objet($visit, $invoice->admissionId),
            'cpt'    => $invoice->counterSalePayer,
            'cons'   => $this->consultation($visit, $tz),
            'ords'   => $this->prescriptions($visit),
            'avoirs' => array_map(fn (CreditNoteSummaryView $note): array => [
                'id'    => $note->id,
                'num'   => $note->number,
                'tot'   => $note->totalGrossMinorUnits,
                'motif' => $note->reason,
                'd'     => $note->issuedAt->setTimezone($tz)->format('Y-m-d'),
                'remb'  => null !== $note->refundMethod ? ['mode' => self::MODES[$note->refundMethod] ?? 'autre', 'l' => $note->refundMethodLabel, 'm' => $note->refundedMinorUnits] : null,
            ], $invoice->creditNotes),
            'lignes' => array_map(fn (InvoiceLineView $line): array => $this->line($line, \in_array($line->id, $credited, true)), $invoice->lines),
            // The breakdown is already discounted: its rows add up to the totals.
            'tva' => array_map(static fn (array $rate): array => [
                't'   => self::rate($rate['ratePercent']),
                'ht'  => $rate['netMinorUnits'],
                'tva' => $rate['taxMinorUnits'],
            ], $invoice->taxBreakdown),
            'remise'  => $invoice->discountMinorUnits > 0 ? ['m' => $invoice->discountMinorUnits, 'motif' => $invoice->discountReason] : null,
            'tot'     => $invoice->totalGrossMinorUnits,
            'paye'    => max(0, $paid),
            'credite' => $invoice->creditedMinorUnits,
            'du'      => max(0, $invoice->balanceDueMinorUnits),
            'credit'  => max(0, -$invoice->balanceDueMinorUnits),
            'pays'    => array_map(fn (PaymentView $payment): array => $this->payment($payment, $team, $tz), $invoice->payments),
            'nature'  => self::nature($invoice->lines),
            'annul'   => null !== $invoice->cancelledAt ? ['d' => $invoice->cancelledAt->setTimezone($tz)->format('Y-m-d'), 'motif' => $invoice->cancellationReason] : null,
            'hist'    => $this->invoiceHistory($invoice, $team, $tz),
            'tx'      => TransmissionBadge::of($invoice->transmission),
            'pdf'     => $this->urls->generate('clinic_invoice_pdf', ['invoiceId' => $invoice->id]),
            'actions' => $this->actions($invoice, $visit, $credited, $paid),
        ];
    }

    /**
     * @param array<string, array{id: string, n: string, ini: string, c: string}> $team
     *
     * @return array<string, mixed>|null null when the credit note is not this clinic's
     */
    public function creditNote(string $clinicId, string $creditNoteId, array $team, \DateTimeZone $tz): ?array
    {
        try {
            $note = $this->queryBus->ask(new GetCreditNoteDetails($clinicId, $creditNoteId));
        } catch (\LogicException) {
            return null;
        }

        if (!$note instanceof CreditNoteDetails) {
            return null;
        }

        $visit = $this->visit($note->admissionId);
        $at    = $note->issuedAt->setTimezone($tz);
        $remb  = null !== $note->refundMethod
            ? ['mode' => self::MODES[$note->refundMethod] ?? 'autre', 'l' => $note->refundMethodLabel, 'm' => $note->refundedMinorUnits]
            : null;
        $hist = [['d' => $at->format('Y-m-d'), 'h' => $at->format('H:i'), 't' => 'Avoir établi', 's' => $note->reason]];

        if (null !== $remb) {
            $hist[] = ['d' => $at->format('Y-m-d'), 'h' => $at->format('H:i'), 't' => \sprintf('Remboursé %s · %s', self::euros($note->refundedMinorUnits), $remb['l']), 's' => 'Sur ' . $note->invoiceNumber];
        }

        return [
            'id'     => $note->id,
            'kind'   => 'av',
            'num'    => $note->number,
            'st'     => 'av',
            'raw'    => 'CREDIT_NOTE',
            'd'      => $at->format('Y-m-d'),
            'h'      => $at->format('H:i'),
            'cli'    => $this->client($note->payerClientId, $note->payerName, $note->payerAddressLines, $note->payerAttention),
            'vet'    => null !== $visit && null !== $visit['vetId'] ? ($team[$visit['vetId']] ?? null) : null,
            'anis'   => null !== $visit ? [BillingRegisterViewBuilder::animal($visit['animal'])] : [],
            'objet'  => \sprintf('Avoir sur %s · %s', $note->invoiceNumber, $note->reason),
            'origin' => [
                'id'  => $note->invoiceId,
                'num' => $note->invoiceNumber,
                'd'   => $note->invoiceIssuedAt?->setTimezone($tz)->format('Y-m-d'),
            ],
            'motif'  => $note->reason,
            'remb'   => $remb,
            'lignes' => array_map(fn (InvoiceLineView $line): array => $this->line($line, false), $note->lines),
            'tva'    => array_map(static fn (array $rate): array => [
                't'   => self::rate($rate['ratePercent']),
                'ht'  => $rate['netMinorUnits'],
                'tva' => $rate['taxMinorUnits'],
            ], $note->taxBreakdown),
            'remise'  => null,
            'tot'     => $note->totalGrossMinorUnits,
            'nature'  => self::nature($note->lines),
            'hist'    => $hist,
            'tx'      => TransmissionBadge::of($note->transmission),
            'pdf'     => $this->urls->generate('clinic_credit_note_pdf', ['creditNoteId' => $note->id]),
            'actions' => [],
        ];
    }

    /**
     * « 20 », « 5,5 »: a VAT rate as the paper prints it.
     */
    public static function rate(string $percent): string
    {
        $trimmed = str_contains($percent, '.') ? mb_rtrim(mb_rtrim($percent, '0'), '.') : $percent;

        return str_replace('.', ',', '' !== $trimmed ? $trimmed : '0');
    }

    /**
     * « Opération : prestation de services » — what the legal mention says the
     * invoice is for: acts are services, everything else is goods.
     *
     * @param list<InvoiceLineView> $lines
     */
    public static function nature(array $lines): string
    {
        $goods    = false;
        $services = false;

        foreach ($lines as $line) {
            if (null === $line->taxCategoryCode || str_starts_with($line->taxCategoryCode, 'veterinary.act')) {
                $services = true;
            } else {
                $goods = true;
            }
        }

        return match (true) {
            $goods && $services => 'livraison de biens et prestation de services',
            $goods              => 'livraison de biens',
            default             => 'prestation de services',
        };
    }

    public static function euros(int $minorUnits): string
    {
        return number_format($minorUnits / 100, 2, ',', ' ') . ' €';
    }

    /**
     * @return array{
     *     consultationId: ?string, open: bool, vetId: ?string, startedAt: ?string,
     *     motifs: list<string>, complaint: ?string,
     *     animal: array{id: ?string, name: string, species: ?string}
     * }|null
     */
    private function visit(?string $admissionId): ?array
    {
        return null !== $admissionId ? ($this->visits->visits([$admissionId])[$admissionId] ?? null) : null;
    }

    /**
     * @param list<string> $addressLines
     *
     * @return array{n: string, att: ?string, court: string, tel: ?string, adr: list<string>, url: ?string}
     */
    private function client(?string $clientId, string $name, array $addressLines, ?string $attention = null): array
    {
        return [
            'n'     => $name,
            'att'   => $attention,
            'court' => (string) preg_replace('/^(M\.|Mme|Mlle|Dr) /u', '', $name),
            'tel'   => $this->phone(null !== $clientId ? ($this->visits->phones([$clientId])[$clientId] ?? null) : null),
            'adr'   => $addressLines,
            'url'   => null !== $clientId ? $this->urls->generate('clinic_clients_view', ['id' => $clientId]) : null,
        ];
    }

    private function phone(?string $e164): ?string
    {
        return null !== $e164 ? html_entity_decode($this->phones->phoneDisplay($e164, false, false), \ENT_QUOTES) : null;
    }

    /**
     * @param array{consultationId: ?string, open: bool, startedAt: ?string, motifs: list<string>, complaint: ?string}|null $visit
     *
     * @return array{label: string, d: ?string, open: bool, url: string}|null
     */
    private function consultation(?array $visit, \DateTimeZone $tz): ?array
    {
        if (null === $visit || null === $visit['consultationId']) {
            return null;
        }

        return [
            'label' => [] !== $visit['motifs'] ? implode(' · ', $visit['motifs']) : ($visit['complaint'] ?? 'Consultation'),
            'd'     => null !== $visit['startedAt'] ? new \DateTimeImmutable($visit['startedAt'], new \DateTimeZone('UTC'))->setTimezone($tz)->format('Y-m-d') : null,
            'open'  => $visit['open'],
            'url'   => $this->urls->generate('clinic_consultation_details', ['id' => $visit['consultationId']]),
        ];
    }

    /**
     * @param array{consultationId: ?string}|null $visit
     *
     * @return list<array{num: string, url: string}>
     */
    private function prescriptions(?array $visit): array
    {
        if (null === $visit || null === $visit['consultationId']) {
            return [];
        }

        return array_map(fn (array $p): array => [
            'num' => $p['number'],
            'url' => $this->urls->generate('clinic_prescription_details', ['prescriptionId' => $p['id']]),
        ], $this->visits->prescriptions([$visit['consultationId']])[$visit['consultationId']] ?? []);
    }

    /**
     * @return array<string, mixed>
     */
    private function line(InvoiceLineView $line, bool $credited): array
    {
        $quantity = str_contains($line->quantity, '.') ? mb_rtrim(mb_rtrim($line->quantity, '0'), '.') : $line->quantity;
        $q        = (float) $line->quantity;

        return [
            'id'       => $line->id,
            'lib'      => $line->label,
            'code'     => $line->code,
            'q'        => str_replace('.', ',', $quantity),
            'pu'       => 0.0 !== $q ? (int) round($line->grossMinorUnits / $q) : $line->grossMinorUnits,
            't'        => self::rate($line->taxRatePercent),
            'tot'      => $line->grossMinorUnits,
            'credited' => $credited,
        ];
    }

    /**
     * @param array<string, array{id: string, n: string, ini: string, c: string}> $team
     *
     * @return array<string, mixed>
     */
    private function payment(PaymentView $payment, array $team, \DateTimeZone $tz): array
    {
        $at = $payment->receivedAt->setTimezone($tz);

        return [
            'mode'  => self::MODES[$payment->method] ?? 'autre',
            'l'     => $payment->methodLabel,
            'd'     => $at->format('Y-m-d'),
            'h'     => $at->format('H:i'),
            'm'     => $payment->amountMinorUnits,
            'ref'   => $payment->reference,
            'par'   => null !== $payment->recordedByUserId ? ($team[$payment->recordedByUserId]['n'] ?? null) : null,
            'remis' => $payment->depositedAt?->setTimezone($tz)->format('Y-m-d'),
        ];
    }

    /**
     * What happened to the invoice, oldest first.
     *
     * @param array<string, array{id: string, n: string, ini: string, c: string}> $team
     *
     * @return list<array{d: string, h: string, t: string, s: string}>
     */
    private function invoiceHistory(InvoiceDetails $invoice, array $team, \DateTimeZone $tz): array
    {
        $entry = static fn (\DateTimeImmutable $at, string $t, string $s): array => [
            'd' => $at->setTimezone($tz)->format('Y-m-d'),
            'h' => $at->setTimezone($tz)->format('H:i'),
            't' => $t,
            's' => $s,
        ];

        $hist = [];

        if (null === $invoice->issuedAt) {
            $hist[] = $entry($invoice->createdAt, 'Brouillon préparé', 'Numéro attribué à l\'encaissement');
        } else {
            $hist[] = $entry($invoice->issuedAt, 'Émise', null !== $invoice->number ? 'N° ' . $invoice->number : '');
        }

        if ('REFUSED' === $invoice->counterSalePayer) {
            $hist[] = $entry($invoice->issuedAt ?? $invoice->createdAt, 'Vente sans coordonnées', 'Refus du client, tracé au journal de caisse');
        }

        foreach ($invoice->payments as $payment) {
            $by     = null !== $payment->recordedByUserId ? ($team[$payment->recordedByUserId]['n'] ?? null) : null;
            $hist[] = $entry($payment->receivedAt, \sprintf('Encaissé %s · %s', self::euros($payment->amountMinorUnits), $payment->methodLabel), $payment->reference ?? $by ?? 'Accueil');

            if (null !== $payment->depositedAt) {
                $hist[] = $entry($payment->depositedAt, 'Chèque remis en banque', \sprintf('%s · bordereau de remise', self::euros($payment->amountMinorUnits)));
            }
        }

        foreach ($invoice->creditNotes as $note) {
            $hist[] = $entry($note->issuedAt, 'Avoir ' . $note->number, \sprintf('%s · %s', self::euros($note->totalGrossMinorUnits), $note->reason));

            if ($note->refundedMinorUnits > 0) {
                $hist[] = $entry($note->issuedAt, \sprintf('Remboursé %s · %s', self::euros($note->refundedMinorUnits), $note->refundMethodLabel ?? ''), 'Par l\'avoir ' . $note->number);
            }
        }

        if (null !== $invoice->cancelledAt) {
            $hist[] = $entry($invoice->cancelledAt, 'Annulée', $invoice->cancellationReason ?? '');
        }

        usort($hist, static fn (array $a, array $b): int => ($a['d'] . $a['h']) <=> ($b['d'] . $b['h']));

        return $hist;
    }

    /**
     * The acts still open on the invoice, each with its form target.
     *
     * @param array{consultationId: ?string}|null $visit
     * @param list<string>                        $credited
     *
     * @return array<string, mixed>
     */
    private function actions(InvoiceDetails $invoice, ?array $visit, array $credited, int $paid): array
    {
        $form = fn (string $route, array $params, string $intent): array => [
            'url'   => $this->urls->generate($route, $params),
            'token' => $this->csrf->getToken($intent)->getValue(),
        ];

        $actions = [
            'consultation' => null !== $visit && null !== $visit['consultationId']
                ? $this->urls->generate('clinic_consultation_details', ['id' => $visit['consultationId']])
                : null,
        ];

        if ('DRAFT' === $invoice->status && null !== $invoice->admissionId) {
            $actions['checkout'] = $form('clinic_checkout_submit', ['admissionId' => $invoice->admissionId], 'checkout_' . $invoice->admissionId) + [
                'hasLines' => [] !== $invoice->lines,
            ];
        }

        if (\in_array($invoice->status, ['ISSUED', 'PARTIALLY_PAID'], true) && $invoice->balanceDueMinorUnits > 0) {
            $actions['pay'] = $form('clinic_invoice_pay', ['invoiceId' => $invoice->id], 'invoice_pay_' . $invoice->id);
        }

        $creditable = \in_array($invoice->status, ['ISSUED', 'PARTIALLY_PAID', 'PAID'], true)
            && $invoice->creditableMinorUnits > 0
            && \count($credited) < \count($invoice->lines);

        if ($creditable) {
            $actions['avoir'] = $form('clinic_invoice_credit_note', ['invoiceId' => $invoice->id], 'invoice_credit_note_' . $invoice->id) + [
                'paid' => $paid > 0,
            ];
        }

        return $actions;
    }
}
