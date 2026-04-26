<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Application\Search;

use App\Context\Client\Application\Search\ClientSearchIndexWriterInterface;
use App\Context\Client\Application\Search\OnClientArchived;
use App\Context\Client\Domain\Event\ClientArchived;
use PHPUnit\Framework\TestCase;

final class OnClientArchivedTest extends TestCase
{
    public function testItMarksArchivedOnClientArchived(): void
    {
        $clientId = '01912345-6789-7abc-8def-000000000001';
        $clinicId = '01912345-6789-7abc-8def-000000000002';

        $writer = $this->createMock(ClientSearchIndexWriterInterface::class);
        $writer->expects(self::once())
            ->method('markArchived')
            ->with($clientId, $clinicId)
        ;

        $event   = new ClientArchived($clientId, $clinicId);
        $handler = new OnClientArchived($writer);
        $handler($event);
    }
}
