<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Application\Command\RepointDocumentPatient;

use App\Context\ClientPortal\Application\Command\RepointDocumentPatient\RepointDocumentPatient;
use App\Context\ClientPortal\Application\Command\RepointDocumentPatient\RepointDocumentPatientHandler;
use App\Context\ClientPortal\Domain\Document\DocumentType;
use App\Tests\Unit\Context\ClientPortal\Support\DocumentWorld as W;
use PHPUnit\Framework\TestCase;

final class RepointDocumentPatientHandlerTest extends TestCase
{
    private const string TARGET = '01960000-0000-7000-8000-0000000f2002';
    private const string DOC2   = '01960000-0000-7000-8000-0000000f0002';

    public function testTheDocumentsFollowTheTargetPatientAndItsAnimal(): void
    {
        $world = new W();
        $world->document(DocumentType::INVOICE);
        $world->document(DocumentType::REPORT, id: self::DOC2, sourceRef: self::DOC2, petIds: ['other-animal']);
        $world->sources->animals[self::TARGET] = W::ANIMAL;

        $this->handler($world)(new RepointDocumentPatient(W::CLINIC, W::PATIENT, self::TARGET));

        self::assertSame([self::TARGET, [W::ANIMAL]], [
            $world->documents->documents[W::DOC]->patientId(),
            $world->documents->documents[W::DOC]->petIds(),
        ]);
        self::assertSame([self::TARGET, ['other-animal']], [
            $world->documents->documents[self::DOC2]->patientId(),
            $world->documents->documents[self::DOC2]->petIds(),
        ]);
        self::assertSame(2, $world->documents->saves);
    }

    public function testATargetWithoutAnimalLeavesThePetsAsTheyAre(): void
    {
        $world = new W();
        $world->document(DocumentType::INVOICE);

        $this->handler($world)(new RepointDocumentPatient(W::CLINIC, W::PATIENT, self::TARGET));

        self::assertSame([self::TARGET, []], [
            $world->documents->only()->patientId(),
            $world->documents->only()->petIds(),
        ]);
    }

    private function handler(W $world): RepointDocumentPatientHandler
    {
        return new RepointDocumentPatientHandler($world->documents, $world->sources, $world->clock);
    }
}
