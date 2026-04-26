<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Application\Search;

use App\Context\Client\Application\Search\ClientSearchEntryData;
use App\Context\Client\Application\Search\ClientSearchEntryWriterInterface;
use App\Context\Client\Application\Search\OnClientCreated;
use App\Context\Client\Domain\Event\ClientCreated;
use PHPUnit\Framework\TestCase;

final class OnClientCreatedTest extends TestCase
{
    public function testItUpsertsClientIndexOnClientCreated(): void
    {
        $clientId = '01912345-6789-7abc-8def-000000000001';
        $clinicId = '01912345-6789-7abc-8def-000000000002';

        $writer = $this->createMock(ClientSearchEntryWriterInterface::class);
        $writer->expects(self::once())
            ->method('upsert')
            ->with(self::callback(static function (ClientSearchEntryData $data) use ($clientId, $clinicId): bool {
                return $data->clientId === $clientId
                    && $data->clinicId === $clinicId
                    && 'Marie' === $data->firstName
                    && 'Curie' === $data->lastName
                    && '0612345678' === $data->phone
                    && 'marie@example.com' === $data->email
                    && 'active' === $data->status;
            }))
        ;

        $event = new ClientCreated(
            clientId: $clientId,
            clinicId: $clinicId,
            firstName: 'Marie',
            lastName: 'Curie',
            contactMethods: [
                ['type' => 'phone', 'label' => 'mobile', 'value' => '0612345678', 'isPrimary' => true],
                ['type' => 'email', 'label' => 'pro', 'value' => 'marie@example.com', 'isPrimary' => true],
            ],
        );

        $handler = new OnClientCreated($writer);
        $handler($event);
    }
}
