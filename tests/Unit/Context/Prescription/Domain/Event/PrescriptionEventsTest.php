<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Prescription\Domain\Event;

use App\Context\Prescription\Domain\Event\PrescriptionCancelled;
use App\Context\Prescription\Domain\Event\PrescriptionDrafted;
use App\Context\Prescription\Domain\Event\PrescriptionIssued;
use App\Context\Prescription\Domain\Event\PrescriptionRenewed;
use PHPUnit\Framework\TestCase;

/**
 * Events are what the rest of the system reads about prescriptions, so their
 * name, their aggregate and every key of their payload are asserted verbatim.
 */
final class PrescriptionEventsTest extends TestCase
{
    private const string PRESCRIPTION_ID = '01960000-0000-7000-8000-000000000101';
    private const string CLINIC_ID       = '01960000-0000-7000-8000-000000000102';
    private const string CONSULTATION_ID = '01960000-0000-7000-8000-000000000103';
    private const string AT              = '2026-08-22T10:00:00+00:00';

    public function testPrescriptionDrafted(): void
    {
        $event = new PrescriptionDrafted(self::PRESCRIPTION_ID, self::CLINIC_ID, self::CONSULTATION_ID, self::AT);

        self::assertSame('prescription.prescription.drafted.v1', $event->name());
        self::assertSame(self::PRESCRIPTION_ID, $event->aggregateId());
        self::assertSame([
            'prescriptionId' => self::PRESCRIPTION_ID,
            'clinicId'       => self::CLINIC_ID,
            'consultationId' => self::CONSULTATION_ID,
            'draftedAt'      => self::AT,
        ], $event->payload());
    }

    public function testPrescriptionIssued(): void
    {
        $event = new PrescriptionIssued(
            self::PRESCRIPTION_ID,
            self::CLINIC_ID,
            self::CONSULTATION_ID,
            'ORD-2026-000001',
            2,
            true,
            '2026-09-19T10:00:00+00:00',
            self::AT,
        );

        self::assertSame('prescription.prescription.issued.v1', $event->name());
        self::assertSame(self::PRESCRIPTION_ID, $event->aggregateId());
        self::assertSame([
            'prescriptionId'             => self::PRESCRIPTION_ID,
            'clinicId'                   => self::CLINIC_ID,
            'consultationId'             => self::CONSULTATION_ID,
            'number'                     => 'ORD-2026-000001',
            'lineCount'                  => 2,
            'carriesControlledSubstance' => true,
            'validUntil'                 => '2026-09-19T10:00:00+00:00',
            'issuedAt'                   => self::AT,
        ], $event->payload());
    }

    public function testPrescriptionRenewed(): void
    {
        $event = new PrescriptionRenewed(
            self::PRESCRIPTION_ID,
            self::CLINIC_ID,
            1,
            1,
            '2028-08-21T10:00:00+00:00',
            self::AT,
        );

        self::assertSame('prescription.prescription.renewed.v1', $event->name());
        self::assertSame(self::PRESCRIPTION_ID, $event->aggregateId());
        self::assertSame([
            'prescriptionId'    => self::PRESCRIPTION_ID,
            'clinicId'          => self::CLINIC_ID,
            'renewalsUsed'      => 1,
            'renewalsRemaining' => 1,
            'newValidUntil'     => '2028-08-21T10:00:00+00:00',
            'renewedAt'         => self::AT,
        ], $event->payload());
    }

    public function testPrescriptionCancelled(): void
    {
        $event = new PrescriptionCancelled(self::PRESCRIPTION_ID, self::CLINIC_ID, 'Erreur de posologie', self::AT);

        self::assertSame('prescription.prescription.cancelled.v1', $event->name());
        self::assertSame(self::PRESCRIPTION_ID, $event->aggregateId());
        self::assertSame([
            'prescriptionId' => self::PRESCRIPTION_ID,
            'clinicId'       => self::CLINIC_ID,
            'reason'         => 'Erreur de posologie',
            'cancelledAt'    => self::AT,
        ], $event->payload());
    }
}
