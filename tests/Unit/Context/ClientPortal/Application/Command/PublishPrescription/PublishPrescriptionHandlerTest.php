<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Application\Command\PublishPrescription;

use App\Context\ClientPortal\Application\Command\PublishPrescription\PublishPrescription;
use App\Context\ClientPortal\Application\Command\PublishPrescription\PublishPrescriptionHandler;
use App\Context\ClientPortal\Application\Port\PrescriptionPublication;
use App\Context\ClientPortal\Application\Port\ReportShareability;
use App\Context\ClientPortal\Domain\Document\DocumentType;
use App\Context\ClientPortal\Domain\Document\PublicationStatus;
use App\Tests\Unit\Context\ClientPortal\Support\DocumentWorld as W;
use PHPUnit\Framework\TestCase;

final class PublishPrescriptionHandlerTest extends TestCase
{
    private const string PRESCRIPTION = '01960000-0000-7000-8000-0000000b0001';
    private const string CONSULT      = '01960000-0000-7000-8000-0000000b2001';

    public function testAPrescriptionThatIsNotPublishableIsSkipped(): void
    {
        $world = new W();

        $this->handler($world)(new PublishPrescription(W::CLINIC, self::PRESCRIPTION));

        self::assertSame([], $world->documents->documents);
    }

    public function testALinkedOwnerGetsThePrescriptionWithItsPrescriberAndPatient(): void
    {
        $world = new W();
        $world->link();
        $this->prescription($world, W::ANIMAL);
        $world->sources->shareabilities[self::CONSULT] = new ReportShareability(
            ReportShareability::NOT_CLOSED,
            W::PATIENT,
            W::ANIMAL,
            W::CLIENT,
            new \DateTimeImmutable('2026-10-20 09:00'),
            null,
            'Dr Durand',
        );

        $this->handler($world)(new PublishPrescription(W::CLINIC, self::PRESCRIPTION));

        $document = $world->documents->only();
        self::assertSame(
            [
                W::CLIENT, DocumentType::PRESCRIPTION, self::PRESCRIPTION, self::CONSULT, W::PATIENT, [W::ANIMAL],
                'Ordonnance ORD-7', 'Dr Martin', PublicationStatus::PENDING_FILE,
            ],
            [
                $document->clientId(), $document->type(), $document->sourceRef(), $document->consultationId(),
                $document->patientId(), $document->petIds(), $document->title(), $document->practitionerName(),
                $document->status(),
            ],
        );
        self::assertSame(['store:' . $document->id()->toString() . ':1'], $world->sources->jobs);
    }

    public function testAnUnknownConsultationAndNoAnimalLeaveThemEmpty(): void
    {
        $world = new W();
        $this->prescription($world, null);

        $this->handler($world)(new PublishPrescription(W::CLINIC, self::PRESCRIPTION));

        $document = $world->documents->only();
        self::assertSame(
            [null, [], PublicationStatus::AWAITING_LINK],
            [$document->patientId(), $document->petIds(), $document->status()],
        );
        self::assertSame([], $world->sources->jobs);
    }

    private function prescription(W $world, ?string $animalId): void
    {
        $world->prescriptions->publications[self::PRESCRIPTION] = new PrescriptionPublication(
            self::PRESCRIPTION,
            W::CLIENT,
            'ORD-7',
            new \DateTimeImmutable('2026-10-20 10:00'),
            self::CONSULT,
            $animalId,
            'Dr Martin',
        );
    }

    private function handler(W $world): PublishPrescriptionHandler
    {
        return new PublishPrescriptionHandler(
            $world->prescriptions,
            $world->sources,
            $world->publisher(),
            $world->fakes,
            $world->clock,
        );
    }
}
