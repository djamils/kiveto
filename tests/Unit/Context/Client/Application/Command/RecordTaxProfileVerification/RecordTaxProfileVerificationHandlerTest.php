<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Application\Command\RecordTaxProfileVerification;

use App\Context\Client\Application\Command\RecordTaxProfileVerification\RecordTaxProfileVerification;
use App\Context\Client\Application\Command\RecordTaxProfileVerification\RecordTaxProfileVerificationHandler;
use App\Context\Client\Domain\Client;
use App\Context\Client\Domain\Event\ClientTaxProfileVerified;
use App\Context\Client\Domain\Repository\ClientRepositoryInterface;
use App\Context\Client\Domain\TaxProfile\CompanyCheckOutcome;
use App\Context\Client\Domain\TaxProfile\TaxProfileVerification;
use App\Context\Client\Domain\TaxProfile\VatCheckOutcome;
use App\Context\Client\Domain\ValueObject\ClientId;
use App\Context\Client\Domain\ValueObject\ClientIdentity;
use App\Context\Client\Domain\ValueObject\ClientStatus;
use App\Context\Client\Domain\ValueObject\ClientTaxProfile;
use App\Context\Client\Domain\ValueObject\ClinicId;
use App\Context\Client\Domain\ValueObject\ContactLabel;
use App\Context\Client\Domain\ValueObject\ContactMethod;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\ValueObject\EmailAddress;
use App\Shared\Domain\ValueObject\FrenchVatNumber;
use App\Shared\Domain\ValueObject\Siren;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RecordTaxProfileVerificationHandler::class)]
final class RecordTaxProfileVerificationHandlerTest extends TestCase
{
    private const string CLINIC_ID = '12345678-9abc-def0-1234-56789abcdef0';
    private const string CLIENT_ID = '01234567-89ab-cdef-0123-456789abcdef';

    public function testTheAnswerIsStoredSavedAndPublished(): void
    {
        $client     = self::client();
        $published  = [];
        $repository = $this->createMock(ClientRepositoryInterface::class);
        $repository->method('find')->willReturn($client);
        $repository->expects(self::once())->method('save')->with($client);

        $eventBus = $this->createMock(EventBusInterface::class);
        $eventBus->expects(self::once())->method('publish')->willReturnCallback(static function (array $stamps, object ...$events) use (&$published): void {
            $published = $events;
        });

        new RecordTaxProfileVerificationHandler($repository, new DomainEventPublisher($eventBus))(self::command('523456788'));

        $verification = $client->taxProfileVerification();
        self::assertSame(CompanyCheckOutcome::ACTIVE, $verification->company()->outcome());
        self::assertSame('Élevage des Calanques', $verification->company()->registryName());
        self::assertSame('2026-10-19T10:00:00+00:00', $verification->company()->checkedAt()?->format(\DATE_ATOM));
        self::assertSame(VatCheckOutcome::VALID, $verification->vat()->outcome());
        self::assertNull($verification->requestedAt());
        self::assertCount(1, $published);
        self::assertInstanceOf(ClientTaxProfileVerified::class, $published[0]);
    }

    public function testALateAnswerSavesNothing(): void
    {
        $repository = $this->createMock(ClientRepositoryInterface::class);
        $repository->method('find')->willReturn(self::client());
        $repository->expects(self::never())->method('save');

        $eventBus = $this->createMock(EventBusInterface::class);
        $eventBus->expects(self::never())->method('publish');

        new RecordTaxProfileVerificationHandler($repository, new DomainEventPublisher($eventBus))(self::command('441569738'));
    }

    public function testAClientGoneMeanwhileIsIgnored(): void
    {
        $repository = $this->createMock(ClientRepositoryInterface::class);
        $repository->method('find')->willReturn(null);
        $repository->expects(self::never())->method('save');

        new RecordTaxProfileVerificationHandler($repository, new DomainEventPublisher($this->createStub(EventBusInterface::class)))(self::command('523456788'));
    }

    private static function command(string $checkedSiren): RecordTaxProfileVerification
    {
        return new RecordTaxProfileVerification(
            clinicId: self::CLINIC_ID,
            clientId: self::CLIENT_ID,
            checkedSiren: $checkedSiren,
            checkedVatNumber: 'FR31523456788',
            companyOutcome: 'active',
            registryName: 'Élevage des Calanques',
            companyCheckedAt: '2026-10-19T10:00:00+00:00',
            vatOutcome: 'valid',
            vatCheckedAt: '2026-10-19T10:00:01+00:00',
        );
    }

    private static function client(): Client
    {
        $siren = Siren::fromInput('523456788');

        return Client::reconstitute(
            id: ClientId::fromString(self::CLIENT_ID),
            clinicId: ClinicId::fromString(self::CLINIC_ID),
            identity: new ClientIdentity('Claire', 'Rostand'),
            status: ClientStatus::ACTIVE,
            contactMethods: [ContactMethod::email(EmailAddress::fromString('claire@calanques.fr'), ContactLabel::WORK, true)],
            createdAt: new \DateTimeImmutable('2026-10-01'),
            updatedAt: new \DateTimeImmutable('2026-10-01'),
            taxProfile: ClientTaxProfile::frenchBusiness('Élevage des Calanques', $siren, FrenchVatNumber::forSiren($siren)),
            taxProfileVerification: TaxProfileVerification::unchecked(new \DateTimeImmutable('2026-10-19 09:59:00')),
        );
    }
}
