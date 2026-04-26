<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Application\Search;

use App\Context\Client\Application\Search\ClientSearchIndexWriterInterface;
use App\Context\Client\Application\Search\OnClientContactMethodsReplaced;
use App\Context\Client\Domain\Event\ClientContactMethodsReplaced;
use PHPUnit\Framework\TestCase;

final class OnClientContactMethodsReplacedTest extends TestCase
{
    public function testItUpdatesContactMethodsOnEvent(): void
    {
        $clientId = '01912345-6789-7abc-8def-000000000001';
        $clinicId = '01912345-6789-7abc-8def-000000000002';

        $writer = $this->createMock(ClientSearchIndexWriterInterface::class);
        $writer->expects(self::once())
            ->method('updateContactMethods')
            ->with($clientId, $clinicId, '0612345678', 'test@example.com')
        ;

        $event = new ClientContactMethodsReplaced(
            clientId: $clientId,
            clinicId: $clinicId,
            contactMethods: [
                ['type' => 'phone', 'label' => 'mobile', 'value' => '0612345678', 'isPrimary' => true],
                ['type' => 'email', 'label' => 'pro', 'value' => 'test@example.com', 'isPrimary' => true],
            ],
        );

        $handler = new OnClientContactMethodsReplaced($writer);
        $handler($event);
    }
}
