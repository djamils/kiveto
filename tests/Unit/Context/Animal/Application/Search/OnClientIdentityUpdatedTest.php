<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Animal\Application\Search;

use App\Context\Animal\Application\Search\AnimalSearchIndexWriterInterface;
use App\Context\Animal\Application\Search\OnClientIdentityUpdated;
use App\Context\Client\Domain\Event\ClientIdentityUpdated;
use PHPUnit\Framework\TestCase;

final class OnClientIdentityUpdatedTest extends TestCase
{
    public function testItUpdatesOwnerNameInIndexFromClientIdentityEvent(): void
    {
        $clientId = '01912345-6789-7abc-8def-000000000003';
        $clinicId = '01912345-6789-7abc-8def-000000000002';

        $writer = $this->createMock(AnimalSearchIndexWriterInterface::class);
        $writer->expects(self::once())
            ->method('updateOwnerName')
            ->with($clientId, $clinicId, 'Jean Dupont')
        ;

        $event   = new ClientIdentityUpdated($clientId, $clinicId, 'Jean', 'Dupont');
        $handler = new OnClientIdentityUpdated($writer);
        $handler($event);
    }
}
