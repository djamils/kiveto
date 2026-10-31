<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Application\Command\MarkDocumentRead;

use App\Context\ClientPortal\Application\Command\MarkDocumentRead\MarkDocumentRead;
use App\Context\ClientPortal\Application\Command\MarkDocumentRead\MarkDocumentReadHandler;
use App\Tests\Unit\Context\ClientPortal\Support\DocumentWorld as W;
use PHPUnit\Framework\TestCase;

final class MarkDocumentReadHandlerTest extends TestCase
{
    public function testTheReadIsMarkedNow(): void
    {
        $world = new W();

        new MarkDocumentReadHandler($world->sources, $world->clock)(new MarkDocumentRead(W::USER, W::DOC));

        self::assertSame(['read:' . W::DOC . ':' . W::USER . ':2026-10-29 09:00'], $world->sources->reads);
    }
}
