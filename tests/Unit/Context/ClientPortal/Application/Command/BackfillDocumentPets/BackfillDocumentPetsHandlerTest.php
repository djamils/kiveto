<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Application\Command\BackfillDocumentPets;

use App\Context\ClientPortal\Application\Command\BackfillDocumentPets\BackfillDocumentPets;
use App\Context\ClientPortal\Application\Command\BackfillDocumentPets\BackfillDocumentPetsHandler;
use App\Context\ClientPortal\Domain\Document\DocumentType;
use App\Context\ClientPortal\Domain\Document\PublicationStatus;
use App\Tests\Unit\Context\ClientPortal\Support\DocumentWorld as W;
use PHPUnit\Framework\TestCase;

final class BackfillDocumentPetsHandlerTest extends TestCase
{
    private const string DOC2 = '01960000-0000-7000-8000-0000000f0002';
    private const string DOC3 = '01960000-0000-7000-8000-0000000f0003';

    public function testOnlyThePatientsDocumentsWithoutPetGetTheAnimal(): void
    {
        $world = new W();
        $world->document(DocumentType::INVOICE, PublicationStatus::AVAILABLE);
        $world->document(DocumentType::REPORT, id: self::DOC2, sourceRef: self::DOC2, petIds: ['other-animal']);
        $world->document(DocumentType::REPORT, id: self::DOC3, sourceRef: self::DOC3, patientId: 'another-patient');

        new BackfillDocumentPetsHandler($world->documents, $world->clock)(
            new BackfillDocumentPets(W::CLINIC, W::PATIENT, W::ANIMAL),
        );

        self::assertSame([W::ANIMAL], $world->documents->documents[W::DOC]->petIds());
        self::assertSame('2026-10-29', $world->documents->documents[W::DOC]->updatedAt()->format('Y-m-d'));
        self::assertSame(['other-animal'], $world->documents->documents[self::DOC2]->petIds());
        self::assertSame([], $world->documents->documents[self::DOC3]->petIds());
        self::assertSame(1, $world->documents->saves);
    }
}
