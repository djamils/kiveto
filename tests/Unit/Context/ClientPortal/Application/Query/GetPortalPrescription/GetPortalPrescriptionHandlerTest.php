<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Application\Query\GetPortalPrescription;

use App\Context\ClientPortal\Application\Port\ClinicLabel;
use App\Context\ClientPortal\Application\Port\ListedDocument;
use App\Context\ClientPortal\Application\Port\PrescriptionContent;
use App\Context\ClientPortal\Application\Port\PrescriptionContentLine;
use App\Context\ClientPortal\Application\Query\GetPortalPrescription\GetPortalPrescription;
use App\Context\ClientPortal\Application\Query\GetPortalPrescription\GetPortalPrescriptionHandler;
use App\Tests\Unit\Context\ClientPortal\Support\DocumentWorld as W;
use App\Tests\Unit\Context\ClientPortal\Support\FakePublishedDocumentReadModel;
use PHPUnit\Framework\TestCase;

final class GetPortalPrescriptionHandlerTest extends TestCase
{
    private W $world;
    private FakePublishedDocumentReadModel $model;

    protected function setUp(): void
    {
        $this->world = new W();
        $this->model = new FakePublishedDocumentReadModel();
    }

    public function testAListedPrescriptionWithItsLinesInTheClinicTimeZone(): void
    {
        $this->listed();
        $lines = [new PrescriptionContentLine('Amoxicilline 250 mg', '1 boîte', '1 cp matin et soir')];

        $this->world->prescriptions->contents[W::SOURCE] = new PrescriptionContent(
            W::SOURCE,
            new \DateTimeImmutable('2026-10-20 23:30', new \DateTimeZone('UTC')),
            'Dr Martin',
            W::ANIMAL,
            'Rex',
            $lines,
        );
        $this->world->fakes->clinics[W::CLINIC] = new ClinicLabel('Clinique', 'Paris', 'Europe/Paris');

        $prescription = $this->handler()(new GetPortalPrescription(W::USER, W::CLINIC, W::SOURCE));

        self::assertNotNull($prescription);
        self::assertSame(
            [W::SOURCE, '2026-10-21 01:30', 'Europe/Paris', 'Dr Martin', W::ANIMAL, 'Rex', $lines],
            [
                $prescription->id, $prescription->issuedAt->format('Y-m-d H:i'),
                $prescription->issuedAt->getTimezone()->getName(), $prescription->vet, $prescription->petId,
                $prescription->petName, $prescription->lines,
            ],
        );
        $call = 'findBySource:' . W::USER . ':' . W::CLINIC . ':prescription:' . W::SOURCE;
        self::assertSame([$call], $this->model->calls);
    }

    public function testAnIssuedPrescriptionOutsideTheRegistryOfTheAccountIsNotShown(): void
    {
        $this->world->prescriptions->contents[W::SOURCE] = new PrescriptionContent(
            W::SOURCE,
            new \DateTimeImmutable('2026-10-20'),
            'Dr Martin',
            null,
            'Rex',
            [],
        );

        self::assertNull($this->handler()(new GetPortalPrescription(W::USER, W::CLINIC, W::SOURCE)));
        self::assertSame([], $this->world->prescriptions->contentRequests);
    }

    public function testAListedPrescriptionWithoutContentOrAMalformedIdIsNotShown(): void
    {
        $this->listed();

        self::assertNull($this->handler()(new GetPortalPrescription(W::USER, W::CLINIC, W::SOURCE)));
        self::assertNull($this->handler()(new GetPortalPrescription(W::USER, W::CLINIC, 'ord-1')));
        self::assertSame([W::SOURCE], $this->world->prescriptions->contentRequests);
        self::assertCount(1, $this->model->calls);
    }

    private function listed(): void
    {
        $this->model->bySource['prescription/' . W::SOURCE] = new ListedDocument(
            W::DOC,
            'prescription',
            W::SOURCE,
            'Ordonnance',
            [],
            new \DateTimeImmutable('2026-10-20'),
            false,
            1,
            'client_portal/documents/d-v1.pdf',
            'Dr Martin',
        );
    }

    private function handler(): GetPortalPrescriptionHandler
    {
        return new GetPortalPrescriptionHandler($this->model, $this->world->prescriptions, $this->world->fakes);
    }
}
