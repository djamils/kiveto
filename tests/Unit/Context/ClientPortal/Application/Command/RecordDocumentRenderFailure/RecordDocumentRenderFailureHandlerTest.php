<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Application\Command\RecordDocumentRenderFailure;

use App\Context\ClientPortal\Application\Command\RecordDocumentRenderFailure\RecordDocumentRenderFailure;
use App\Context\ClientPortal\Application\Command\RecordDocumentRenderFailure\RecordDocumentRenderFailureHandler;
use App\Context\ClientPortal\Domain\Document\DocumentType;
use App\Context\ClientPortal\Domain\Document\PublicationStatus;
use App\Tests\Unit\Context\ClientPortal\Support\DocumentWorld as W;
use PHPUnit\Framework\TestCase;
use Symfony\Component\ErrorHandler\BufferingLogger;

final class RecordDocumentRenderFailureHandlerTest extends TestCase
{
    public function testAnUnknownDocumentIsIgnored(): void
    {
        $world  = new W();
        $logger = new BufferingLogger();

        $handler = new RecordDocumentRenderFailureHandler($world->documents, $world->clock, $logger);

        $handler(new RecordDocumentRenderFailure(W::DOC));

        self::assertSame(0, $world->documents->saves);
        self::assertSame([], $logger->cleanLogs());
    }

    public function testTheFifthFailureGivesUpAndIsLogged(): void
    {
        $world   = new W();
        $logger  = new BufferingLogger();
        $handler = new RecordDocumentRenderFailureHandler($world->documents, $world->clock, $logger);
        $world->document(DocumentType::PRESCRIPTION, PublicationStatus::PENDING_FILE, 0, 1);

        for ($i = 1; $i <= 4; ++$i) {
            $handler(new RecordDocumentRenderFailure(W::DOC));
        }

        self::assertSame([PublicationStatus::PENDING_FILE, 4], [
            $world->documents->only()->status(),
            $world->documents->only()->renderAttempts(),
        ]);
        self::assertSame([], $logger->cleanLogs());

        $handler(new RecordDocumentRenderFailure(W::DOC));

        self::assertSame(PublicationStatus::FAILED, $world->documents->only()->status());
        self::assertSame(5, $world->documents->saves);
        self::assertSame(
            [[
                'error',
                'The file of a published document could not be rendered: it is no longer retried.',
                ['documentId' => W::DOC, 'type' => 'prescription', 'clinicId' => W::CLINIC],
            ]],
            $logger->cleanLogs(),
        );
    }
}
