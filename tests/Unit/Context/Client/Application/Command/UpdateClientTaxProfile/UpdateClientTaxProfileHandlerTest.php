<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Application\Command\UpdateClientTaxProfile;

use App\Context\Client\Application\Command\CreateClient\ClientTaxProfileDto;
use App\Context\Client\Application\Command\UpdateClientTaxProfile\UpdateClientTaxProfile;
use App\Context\Client\Application\Command\UpdateClientTaxProfile\UpdateClientTaxProfileHandler;
use App\Context\Client\Domain\Client;
use App\Context\Client\Domain\Event\ClientTaxProfileUpdated;
use App\Context\Client\Domain\Repository\ClientRepositoryInterface;
use App\Context\Client\Domain\ValueObject\ClientId;
use App\Context\Client\Domain\ValueObject\ClientIdentity;
use App\Context\Client\Domain\ValueObject\ClientStatus;
use App\Context\Client\Domain\ValueObject\ClinicId;
use App\Context\Client\Domain\ValueObject\ContactLabel;
use App\Context\Client\Domain\ValueObject\ContactMethod;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\ValueObject\EmailAddress;
use PHPUnit\Framework\TestCase;

final class UpdateClientTaxProfileHandlerTest extends TestCase
{
    private const string CLINIC_ID = '12345678-9abc-def0-1234-56789abcdef0';
    private const string CLIENT_ID = '01234567-89ab-cdef-0123-456789abcdef';

    public function testTheProfileIsUpdatedSavedAndPublished(): void
    {
        $client = Client::reconstitute(
            id: ClientId::fromString(self::CLIENT_ID),
            clinicId: ClinicId::fromString(self::CLINIC_ID),
            identity: new ClientIdentity('Inès', 'Perrin'),
            status: ClientStatus::ACTIVE,
            contactMethods: [ContactMethod::email(EmailAddress::fromString('ines@refuge.fr'), ContactLabel::WORK, true)],
            createdAt: new \DateTimeImmutable('2026-01-01'),
            updatedAt: new \DateTimeImmutable('2026-01-01'),
        );

        $repository = $this->createMock(ClientRepositoryInterface::class);
        $repository->expects(self::once())->method('get')
            ->with(ClinicId::fromString(self::CLINIC_ID), ClientId::fromString(self::CLIENT_ID))
            ->willReturn($client)
        ;
        $repository->expects(self::once())->method('save')->with($client);

        $eventBus = $this->createMock(EventBusInterface::class);
        $eventBus->expects(self::once())->method('publish')->with([], self::isInstanceOf(ClientTaxProfileUpdated::class));

        $clock = $this->createStub(ClockInterface::class);
        $now   = new \DateTimeImmutable('2026-10-17 10:00:00');
        $clock->method('now')->willReturn($now);

        (new UpdateClientTaxProfileHandler($repository, new DomainEventPublisher($eventBus), $clock))(
            new UpdateClientTaxProfile(self::CLINIC_ID, self::CLIENT_ID, new ClientTaxProfileDto('individual', 'Refuge des Collines')),
        );

        self::assertSame('Refuge des Collines', $client->taxProfile()->legalName());
        self::assertSame($now, $client->updatedAt());
    }
}
