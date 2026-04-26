<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Application\Search;

use App\Context\Client\Application\Search\ClientSearchEntryWriterInterface;
use App\Context\Client\Application\Search\OnClientIdentityUpdated;
use App\Context\Client\Domain\Event\ClientIdentityUpdated;
use PHPUnit\Framework\TestCase;

final class OnClientIdentityUpdatedTest extends TestCase
{
    public function testItUpdatesNameOnClientIdentityUpdated(): void
    {
        $clientId = '01912345-6789-7abc-8def-000000000001';
        $clinicId = '01912345-6789-7abc-8def-000000000002';

        $writer = $this->createMock(ClientSearchEntryWriterInterface::class);
        $writer->expects(self::once())
            ->method('updateName')
            ->with($clientId, $clinicId, 'Pierre', 'Martin')
        ;

        $event   = new ClientIdentityUpdated($clientId, $clinicId, 'Pierre', 'Martin');
        $handler = new OnClientIdentityUpdated($writer);
        $handler($event);
    }
}
