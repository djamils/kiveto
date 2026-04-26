<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Application\Search;

use App\Context\Client\Application\Search\ClientSearchEntryWriterInterface;
use App\Context\Client\Application\Search\OnClientUnarchived;
use App\Context\Client\Domain\Event\ClientUnarchived;
use PHPUnit\Framework\TestCase;

final class OnClientUnarchivedTest extends TestCase
{
    public function testItMarksActiveOnClientUnarchived(): void
    {
        $clientId = '01912345-6789-7abc-8def-000000000001';
        $clinicId = '01912345-6789-7abc-8def-000000000002';

        $writer = $this->createMock(ClientSearchEntryWriterInterface::class);
        $writer->expects(self::once())
            ->method('markActive')
            ->with($clientId, $clinicId)
        ;

        $event   = new ClientUnarchived($clientId, $clinicId);
        $handler = new OnClientUnarchived($writer);
        $handler($event);
    }
}
