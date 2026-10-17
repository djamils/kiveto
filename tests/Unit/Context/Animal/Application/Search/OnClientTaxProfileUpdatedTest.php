<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Animal\Application\Search;

use App\Context\Animal\Application\Search\AnimalSearchEntryWriterInterface;
use App\Context\Animal\Application\Search\OnClientTaxProfileUpdated;
use App\Context\Client\Domain\Event\ClientTaxProfileUpdated;
use PHPUnit\Framework\TestCase;

final class OnClientTaxProfileUpdatedTest extends TestCase
{
    private const string CLIENT_ID = '01912345-6789-7abc-8def-000000000003';
    private const string CLINIC_ID = '01912345-6789-7abc-8def-000000000002';

    public function testAStructureIsLabelledByItsLegalName(): void
    {
        $writer = $this->createMock(AnimalSearchEntryWriterInterface::class);
        $writer->expects(self::once())->method('updateOwnerName')->with(self::CLIENT_ID, self::CLINIC_ID, 'Refuge des Collines');

        new OnClientTaxProfileUpdated($writer)($this->event('Refuge des Collines'));
    }

    public function testWithoutLegalNameTheContactNameIsBack(): void
    {
        $writer = $this->createMock(AnimalSearchEntryWriterInterface::class);
        $writer->expects(self::once())->method('updateOwnerName')->with(self::CLIENT_ID, self::CLINIC_ID, 'Inès Perrin');

        new OnClientTaxProfileUpdated($writer)($this->event(null));
    }

    private function event(?string $legalName): ClientTaxProfileUpdated
    {
        return new ClientTaxProfileUpdated(self::CLIENT_ID, self::CLINIC_ID, 'individual', $legalName, null, null, 'Inès', 'Perrin');
    }
}
