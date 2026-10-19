<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Application\Command\RequestTaxProfileVerification;

use App\Context\Client\Application\Command\RequestTaxProfileVerification\RequestTaxProfileVerification;
use App\Context\Client\Application\Command\RequestTaxProfileVerification\RequestTaxProfileVerificationHandler;
use App\Context\Client\Application\Command\VerifyClientTaxProfile\VerifyClientTaxProfile;
use App\Context\Client\Domain\Client;
use App\Context\Client\Domain\Exception\ClientNotFoundException;
use App\Context\Client\Domain\Repository\ClientRepositoryInterface;
use App\Context\Client\Domain\TaxProfile\TaxProfileVerification;
use App\Context\Client\Domain\TaxProfile\VerificationRequestOutcome;
use App\Context\Client\Domain\ValueObject\ClientId;
use App\Context\Client\Domain\ValueObject\ClientIdentity;
use App\Context\Client\Domain\ValueObject\ClientStatus;
use App\Context\Client\Domain\ValueObject\ClientTaxProfile;
use App\Context\Client\Domain\ValueObject\ClinicId;
use App\Context\Client\Domain\ValueObject\ContactLabel;
use App\Context\Client\Domain\ValueObject\ContactMethod;
use App\Shared\Application\Bus\ExternalApiCommandBusInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\ValueObject\EmailAddress;
use App\Shared\Domain\ValueObject\Siren;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(RequestTaxProfileVerificationHandler::class)]
final class RequestTaxProfileVerificationHandlerTest extends TestCase
{
    private const string CLINIC_ID = '12345678-9abc-def0-1234-56789abcdef0';
    private const string CLIENT_ID = '01234567-89ab-cdef-0123-456789abcdef';

    public function testAVerifiableClientIsMarkedAndQueued(): void
    {
        $client     = self::client(ClientStatus::ACTIVE, true, null);
        $repository = $this->createMock(ClientRepositoryInterface::class);
        $repository->method('get')->willReturn($client);
        $repository->expects(self::once())->method('save')->with($client);

        $bus = $this->createMock(ExternalApiCommandBusInterface::class);
        $bus->expects(self::once())->method('send')->with(self::callback(
            static fn (VerifyClientTaxProfile $command): bool => self::CLINIC_ID === $command->clinicId && self::CLIENT_ID === $command->clientId,
        ));

        $outcome = $this->handler($repository, $bus)(new RequestTaxProfileVerification(self::CLINIC_ID, self::CLIENT_ID));

        self::assertSame(VerificationRequestOutcome::QUEUED, $outcome);
        self::assertSame('2026-10-19 10:00:00', $client->taxProfileVerification()->requestedAt()?->format('Y-m-d H:i:s'));
    }

    /**
     * @return iterable<string, array{Client, VerificationRequestOutcome}>
     */
    public static function provideNothingIsQueuedOtherwiseCases(): iterable
    {
        yield 'pending' => [self::client(ClientStatus::ACTIVE, true, '2026-10-19 09:00:00'), VerificationRequestOutcome::ALREADY_PENDING];
        yield 'archived' => [self::client(ClientStatus::ARCHIVED, true, null), VerificationRequestOutcome::ARCHIVED];
        yield 'individual' => [self::client(ClientStatus::ACTIVE, false, null), VerificationRequestOutcome::NOT_APPLICABLE];
    }

    #[DataProvider('provideNothingIsQueuedOtherwiseCases')]
    public function testNothingIsQueuedOtherwise(Client $client, VerificationRequestOutcome $expected): void
    {
        $repository = $this->createMock(ClientRepositoryInterface::class);
        $repository->method('get')->willReturn($client);
        $repository->expects(self::never())->method('save');

        $bus = $this->createMock(ExternalApiCommandBusInterface::class);
        $bus->expects(self::never())->method('send');

        self::assertSame($expected, $this->handler($repository, $bus)(new RequestTaxProfileVerification(self::CLINIC_ID, self::CLIENT_ID)));
    }

    public function testAnUnknownClientIsNotFound(): void
    {
        $repository = $this->createStub(ClientRepositoryInterface::class);
        $repository->method('get')->willThrowException(new ClientNotFoundException(self::CLIENT_ID));

        $bus = $this->createMock(ExternalApiCommandBusInterface::class);
        $bus->expects(self::never())->method('send');

        $this->expectException(ClientNotFoundException::class);

        $this->handler($repository, $bus)(new RequestTaxProfileVerification(self::CLINIC_ID, self::CLIENT_ID));
    }

    private function handler(ClientRepositoryInterface $repository, ExternalApiCommandBusInterface $bus): RequestTaxProfileVerificationHandler
    {
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-10-19 10:00:00'));

        return new RequestTaxProfileVerificationHandler($repository, $bus, $clock);
    }

    private static function client(ClientStatus $status, bool $business, ?string $requestedAt): Client
    {
        return Client::reconstitute(
            id: ClientId::fromString(self::CLIENT_ID),
            clinicId: ClinicId::fromString(self::CLINIC_ID),
            identity: new ClientIdentity('Hugo', 'Marchal'),
            status: $status,
            contactMethods: [ContactMethod::email(EmailAddress::fromString('hugo@lac.fr'), ContactLabel::WORK, true)],
            createdAt: new \DateTimeImmutable('2026-10-01'),
            updatedAt: new \DateTimeImmutable('2026-10-01'),
            taxProfile: $business
                ? ClientTaxProfile::frenchBusiness('Centre équestre du Lac', Siren::fromInput('812345676'), null)
                : ClientTaxProfile::individual('Refuge des Collines'),
            taxProfileVerification: TaxProfileVerification::unchecked(null === $requestedAt ? null : new \DateTimeImmutable($requestedAt)),
        );
    }
}
