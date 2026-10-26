<?php

declare(strict_types=1);

namespace App\Fixtures\Context\Billing\Story;

use App\Context\Billing\Application\Command\ApplyInvoiceDiscount\ApplyInvoiceDiscount;
use App\Context\Billing\Application\Command\CancelInvoice\CancelInvoice;
use App\Context\Billing\Application\Command\IssueInvoice\IssueInvoice;
use App\Context\Billing\Application\Command\PrepareInvoiceForAdmission\PrepareInvoiceForAdmission;
use App\Context\Billing\Application\Command\RecordInvoicePayment\RecordInvoicePayment;
use App\Fixtures\Context\Clinic\Story\ClinicDataStory;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Story;

/**
 * Demo invoices in every status, for both clinics.
 *
 * The visits that already left the board are the ones that get invoiced: they
 * are the only ones whose consultations are finished, which is exactly what the
 * desk charges. Everything goes through the command bus, so the demo data is
 * built by the same invariants as production writes.
 */
final class BillingDemoStory extends Story
{
    private const string PARIS = ClinicDataStory::INDEPENDENT_CLINIC_ID;
    private const string LYON  = ClinicDataStory::GROUP_CLINIC_ID;

    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly Connection $connection,
    ) {
    }

    public function build(): void
    {
        // Only the visits that are over get invoiced. The ones still standing at
        // the counter are left untouched on purpose: the demo's "Encaisser"
        // button has to open a fresh draft, discount and all.
        foreach ([self::PARIS, self::LYON] as $clinicId) {
            $admissions = $this->closedAdmissionsOf($clinicId);

            if (isset($admissions[0])) {
                $this->paidInvoice($clinicId, $admissions[0]);
            }

            if (!isset($admissions[1])) {
                continue;
            }

            // The second visit carries the awkward cases, one per clinic, so
            // the list shows every status.
            if (self::PARIS === $clinicId) {
                // Cancelled by mistake, then re-issued and left unpaid: the
                // client walked out with the invoice in hand.
                $this->cancelledInvoice($clinicId, $admissions[1]);
                $this->issuedInvoice($clinicId, $admissions[1]);

                continue;
            }

            $this->partiallyPaidInvoice($clinicId, $admissions[1]);
        }

        // One visit per clinic leaves with everything: compte rendu, ordonnance
        // and facture. It is the only way the "Dossier complet" of the cockpit
        // has three documents to merge.
        foreach ([self::PARIS, self::LYON] as $clinicId) {
            $admissionId = $this->admissionOfAnIssuedOrdonnance($clinicId);

            if (null !== $admissionId) {
                $this->paidInvoice($clinicId, $admissionId);
            }
        }
    }

    /**
     * The visit of a signed ordonnance that has not been invoiced yet. It is
     * still open at the counter, which is exactly the case the desk meets when
     * it cashes a visit that prescribed something.
     */
    private function admissionOfAnIssuedOrdonnance(string $clinicId): ?string
    {
        $row = $this->connection->fetchOne(
            "SELECT BIN_TO_UUID(c.admission_id)
             FROM consultation__consultations c
             JOIN prescription__prescriptions p
               ON p.consultation_id = c.id AND p.status = 'ISSUED'
             WHERE c.clinic_id = :clinicId
               AND NOT EXISTS (
                   SELECT 1 FROM billing__invoices i WHERE i.admission_id = c.admission_id
               )
             ORDER BY c.started_at_utc ASC
             LIMIT 1",
            ['clinicId' => Uuid::fromString($clinicId)->toBinary()],
        );

        return \is_string($row) ? $row : null;
    }

    private function paidInvoice(string $clinicId, string $admissionId): void
    {
        $invoiceId = $this->prepare($clinicId, $admissionId);

        if (null === $invoiceId) {
            return;
        }

        $this->commandBus->dispatch(new IssueInvoice($clinicId, $invoiceId));

        $this->commandBus->dispatch(new RecordInvoicePayment(
            clinicId: $clinicId,
            invoiceId: $invoiceId,
            method: 'CARD',
            amountMinorUnits: $this->balanceOf($clinicId, $invoiceId),
        ));
    }

    private function issuedInvoice(string $clinicId, string $admissionId): void
    {
        $invoiceId = $this->prepare($clinicId, $admissionId);

        if (null === $invoiceId) {
            return;
        }

        $this->commandBus->dispatch(new IssueInvoice($clinicId, $invoiceId));
    }

    /**
     * Half paid in cash, on a discounted invoice — the two things a receptionist
     * does by hand.
     */
    private function partiallyPaidInvoice(string $clinicId, string $admissionId): void
    {
        $invoiceId = $this->prepare($clinicId, $admissionId);

        if (null === $invoiceId) {
            return;
        }

        $this->commandBus->dispatch(new ApplyInvoiceDiscount(
            clinicId: $clinicId,
            invoiceId: $invoiceId,
            kind: 'PERCENTAGE',
            value: '10',
            reason: 'Client fidèle',
        ));

        $this->commandBus->dispatch(new IssueInvoice($clinicId, $invoiceId));

        $balance = $this->balanceOf($clinicId, $invoiceId);

        if ($balance > 0) {
            $this->commandBus->dispatch(new RecordInvoicePayment(
                clinicId: $clinicId,
                invoiceId: $invoiceId,
                method: 'CASH',
                amountMinorUnits: intdiv($balance, 2),
            ));
        }
    }

    private function cancelledInvoice(string $clinicId, string $admissionId): void
    {
        $invoiceId = $this->prepare($clinicId, $admissionId);

        if (null === $invoiceId) {
            return;
        }

        $this->commandBus->dispatch(new IssueInvoice($clinicId, $invoiceId));
        $this->commandBus->dispatch(new CancelInvoice(
            clinicId: $clinicId,
            invoiceId: $invoiceId,
            reason: 'Facturée deux fois par erreur',
        ));
    }

    private function prepare(string $clinicId, string $admissionId): ?string
    {
        // A visit with nothing recorded has nothing to invoice: preparing it
        // would only leave an empty draft in the register.
        if (!$this->hasBillableLines($admissionId)) {
            return null;
        }

        $invoiceId = $this->commandBus->dispatch(new PrepareInvoiceForAdmission($clinicId, $admissionId));

        if (!\is_string($invoiceId)) {
            return null;
        }

        // A visit with nothing recorded cannot be issued; leave it alone.
        return $this->balanceOf($clinicId, $invoiceId) > 0 ? $invoiceId : null;
    }

    private function hasBillableLines(string $admissionId): bool
    {
        return false !== $this->connection->fetchOne(
            'SELECT 1 FROM consultation__consultations c
             INNER JOIN consultation__billing_lines bl ON bl.consultation_id = c.id
             WHERE c.admission_id = :admissionId LIMIT 1',
            ['admissionId' => Uuid::fromString($admissionId)->toBinary()],
        );
    }

    /**
     * Read straight from the rows rather than through the query bus: loading a
     * whole aggregate for a figure the fixtures only need to hand back as a
     * payment amount costs far more memory than the answer is worth.
     */
    private function balanceOf(string $clinicId, string $invoiceId): int
    {
        $row = $this->connection->fetchAssociative(
            'SELECT i.total_gross,
                    (SELECT COALESCE(SUM(p.amount_minor_units), 0)
                     FROM billing__payments p WHERE p.invoice_id = i.id) AS total_paid
             FROM billing__invoices i
             WHERE i.id = :invoiceId AND i.clinic_id = :clinicId',
            [
                'invoiceId' => Uuid::fromString($invoiceId)->toBinary(),
                'clinicId'  => Uuid::fromString($clinicId)->toBinary(),
            ],
        );

        if (false === $row) {
            return 0;
        }

        return RowAccessor::int($row, 'total_gross') - RowAccessor::int($row, 'total_paid');
    }

    /**
     * Visits that are over — the ones the desk has already cashed up.
     *
     * @return list<string>
     */
    private function closedAdmissionsOf(string $clinicId): array
    {
        return $this->admissionIds(
            "SELECT BIN_TO_UUID(id) AS id FROM admission__admissions
             WHERE clinic_id = :clinicId AND status = 'closed'
             ORDER BY opened_at ASC",
            $clinicId,
        );
    }

    /**
     * @return list<string>
     */
    private function admissionIds(string $sql, string $clinicId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            $sql,
            ['clinicId' => Uuid::fromString($clinicId)->toBinary()],
        );

        $ids = [];

        foreach ($rows as $row) {
            $ids[] = RowAccessor::string($row, 'id');
        }

        return $ids;
    }
}
