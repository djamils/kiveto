<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Application\Search;

use App\Context\Client\Application\Search\ClientSearchEntryWriterInterface;
use App\Context\Client\Application\Search\OnClientTaxProfileUpdated;
use App\Context\Client\Domain\Event\ClientTaxProfileUpdated;
use PHPUnit\Framework\TestCase;

final class OnClientTaxProfileUpdatedTest extends TestCase
{
    public function testTheEntryIsRenamedAfterTheLegalName(): void
    {
        $writer = $this->createMock(ClientSearchEntryWriterInterface::class);
        $writer->expects(self::once())
            ->method('updateName')
            ->with('01912345-6789-7abc-8def-000000000001', '01912345-6789-7abc-8def-000000000002', 'Claire', 'Rostand', 'Élevage des Calanques')
        ;

        new OnClientTaxProfileUpdated($writer)(new ClientTaxProfileUpdated(
            '01912345-6789-7abc-8def-000000000001',
            '01912345-6789-7abc-8def-000000000002',
            'french_business',
            'Élevage des Calanques',
            '523456788',
            null,
            'Claire',
            'Rostand',
        ));
    }
}
