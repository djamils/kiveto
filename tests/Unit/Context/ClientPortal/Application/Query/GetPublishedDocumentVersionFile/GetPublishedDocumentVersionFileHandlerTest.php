<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Application\Query\GetPublishedDocumentVersionFile;

use App\Context\ClientPortal\Application\Query\GetPublishedDocumentVersionFile\GetPublishedDocumentVersionFile;
use App\Context\ClientPortal\Application\Query\GetPublishedDocumentVersionFile\GetPublishedDocumentVersionFileHandler;
use App\Tests\Unit\Context\ClientPortal\Support\DocumentWorld as W;
use App\Tests\Unit\Context\ClientPortal\Support\FakePublishedDocumentReadModel;
use PHPUnit\Framework\TestCase;

final class GetPublishedDocumentVersionFileHandlerTest extends TestCase
{
    public function testTheKeyOfAServedVersion(): void
    {
        $model                             = new FakePublishedDocumentReadModel();
        $model->versionKeys[W::DOC . '/1'] = 'client_portal/documents/d-v1.pdf';
        $handler                           = new GetPublishedDocumentVersionFileHandler($model);

        $key = $handler(new GetPublishedDocumentVersionFile(W::CLINIC, W::DOC, 1));
        self::assertSame('client_portal/documents/d-v1.pdf', $key);
        self::assertNull($handler(new GetPublishedDocumentVersionFile(W::CLINIC, W::DOC, 2)));
        self::assertSame(
            ['versionKey:' . W::CLINIC . ':' . W::DOC . ':1', 'versionKey:' . W::CLINIC . ':' . W::DOC . ':2'],
            $model->calls,
        );
    }

    public function testMalformedSignedValuesAreNeverLookedUp(): void
    {
        $model   = new FakePublishedDocumentReadModel();
        $handler = new GetPublishedDocumentVersionFileHandler($model);

        self::assertNull($handler(new GetPublishedDocumentVersionFile('clinic', W::DOC, 1)));
        self::assertNull($handler(new GetPublishedDocumentVersionFile(W::CLINIC, 'doc', 1)));
        self::assertNull($handler(new GetPublishedDocumentVersionFile(W::CLINIC, W::DOC, 0)));
        self::assertSame([], $model->calls);
    }
}
