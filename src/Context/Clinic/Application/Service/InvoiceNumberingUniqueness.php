<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Service;

use App\Context\Clinic\Domain\Clinic;
use App\Context\Clinic\Domain\Exception\InvoicePrefixAlreadyUsedException;
use App\Context\Clinic\Domain\Repository\ClinicRepositoryInterface;
use App\Context\Clinic\Domain\ValueObject\BillingSettings;
use App\Context\Clinic\Domain\ValueObject\ClinicId;
use App\Context\Clinic\Domain\ValueObject\Letterhead;

/**
 * Several establishments may carry one SIREN, each with its own number series,
 * as long as no two of them number under the same prefix — the default prefix
 * included. A document number is then unique within the company, which is what
 * the electronic invoicing platform identifies a document by.
 *
 * Deliberately a no-worsening rule: a clinic whose company and prefixes stay as
 * they are may still save, even when older data already collides — otherwise
 * nobody could save anything else until the collision is repaired.
 */
final readonly class InvoiceNumberingUniqueness
{
    public function __construct(
        private ClinicRepositoryInterface $clinicRepository,
    ) {
    }

    /** Whether no other establishment of the company numbers under one of these prefixes. */
    public function isUnique(ClinicId $clinicId, Letterhead $letterhead, BillingSettings $settings): bool
    {
        return null === $this->collision($clinicId, $letterhead, $settings);
    }

    /**
     * @throws InvoicePrefixAlreadyUsedException when the new letterhead or settings make two series collide
     */
    public function assertChangeKeepsSeriesDistinct(Clinic $clinic, Letterhead $letterhead, BillingSettings $settings): void
    {
        $current   = $clinic->billingSettings();
        $unchanged = $clinic->letterhead()->siren()?->value() === $letterhead->siren()?->value()
            && $current->effectiveInvoicePrefix() === $settings->effectiveInvoicePrefix()
            && $current->effectiveCreditNotePrefix() === $settings->effectiveCreditNotePrefix();

        if ($unchanged) {
            return;
        }

        $collision = $this->collision($clinic->id(), $letterhead, $settings);

        if (null !== $collision) {
            throw $collision;
        }
    }

    private function collision(ClinicId $clinicId, Letterhead $letterhead, BillingSettings $settings): ?InvoicePrefixAlreadyUsedException
    {
        $siren = $letterhead->siren();

        if (null === $siren) {
            return null;
        }

        $taken = [];

        foreach ($this->clinicRepository->findOthersWithSiren($siren, $clinicId) as $other) {
            $taken[] = $other->billingSettings()->effectiveInvoicePrefix();
            $taken[] = $other->billingSettings()->effectiveCreditNotePrefix();
        }

        $series = [
            InvoicePrefixAlreadyUsedException::SERIES_INVOICE     => $settings->effectiveInvoicePrefix(),
            InvoicePrefixAlreadyUsedException::SERIES_CREDIT_NOTE => $settings->effectiveCreditNotePrefix(),
        ];

        foreach ($series as $kind => $prefix) {
            if (\in_array($prefix, $taken, true)) {
                return new InvoicePrefixAlreadyUsedException($prefix, $kind);
            }
        }

        return null;
    }
}
