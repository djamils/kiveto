<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Application\Command\VerifyClientTaxProfile;

use App\Context\Client\Application\Command\RecordTaxProfileVerification\RecordTaxProfileVerification;
use App\Context\Client\Application\Command\VerifyClientTaxProfile\VerifyClientTaxProfile;
use App\Context\Client\Application\Command\VerifyClientTaxProfile\VerifyClientTaxProfileHandler;
use App\Context\Client\Application\Port\ClientReadRepositoryInterface;
use App\Context\Client\Application\Port\CompanyRegistryInterface;
use App\Context\Client\Application\Port\VatNumberVerifierInterface;
use App\Context\Client\Application\Query\GetClientById\ClientView;
use App\Context\Client\Infrastructure\Registry\FixtureCompanyRegistry;
use App\Context\Client\Infrastructure\Vat\FixtureVatNumberVerifier;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\ExternalApi\ExternalServiceUnavailable;
use App\Shared\Domain\Time\ClockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(VerifyClientTaxProfileHandler::class)]
#[CoversClass(VerifyClientTaxProfile::class)]
#[CoversClass(RecordTaxProfileVerification::class)]
final class VerifyClientTaxProfileHandlerTest extends TestCase
{
    private const string CLINIC_ID = '12345678-9abc-def0-1234-56789abcdef0';
    private const string CLIENT_ID = '01234567-89ab-cdef-0123-456789abcdef';

    /** @var list<object> */
    private array $dispatched = [];

    public function testBothServicesAnswer(): void
    {
        $record = $this->verify(self::view('523456788', 'FR31523456788'));

        self::assertSame(self::CLINIC_ID, $record->clinicId);
        self::assertSame(self::CLIENT_ID, $record->clientId);
        self::assertSame('523456788', $record->checkedSiren);
        self::assertSame('FR31523456788', $record->checkedVatNumber);
        self::assertSame('active', $record->companyOutcome);
        self::assertSame('Élevage des Calanques', $record->registryName);
        self::assertSame('2026-10-19T10:00:00+00:00', $record->companyCheckedAt);
        self::assertSame('valid', $record->vatOutcome);
        self::assertSame('2026-10-19T10:00:00+00:00', $record->vatCheckedAt);
    }

    public function testARegistryOutageKeepsTheViesAnswer(): void
    {
        $registry = $this->createStub(CompanyRegistryInterface::class);
        $registry->method('findBySiren')->willThrowException(ExternalServiceUnavailable::failed('registry', 503));

        $record = $this->verify(self::view('523456788', 'FR31523456788'), registry: $registry);

        self::assertSame('unavailable', $record->companyOutcome);
        self::assertNull($record->registryName);
        self::assertSame('valid', $record->vatOutcome);
    }

    public function testAViesOutageKeepsTheRegistryAnswer(): void
    {
        $vies = $this->createStub(VatNumberVerifierInterface::class);
        $vies->method('verify')->willThrowException(ExternalServiceUnavailable::refused('vies', 'MS_UNAVAILABLE'));

        $record = $this->verify(self::view('523456788', 'FR31523456788'), vies: $vies);

        self::assertSame('active', $record->companyOutcome);
        self::assertSame('unavailable', $record->vatOutcome);
    }

    public function testAnUnknownSirenIsNotFoundAndAnUnknownVatInvalid(): void
    {
        $record = $this->verify(self::view('123456782', 'FR11123456782'));

        self::assertSame('not_found', $record->companyOutcome);
        self::assertNull($record->registryName);
        self::assertSame('invalid', $record->vatOutcome);
    }

    public function testACeasedCompanyKeepsItsName(): void
    {
        $record = $this->verify(self::view('441569738', 'FR14441569738'));

        self::assertSame('ceased', $record->companyOutcome);
        self::assertSame('GAEC du Mont', $record->registryName);
    }

    public function testAConfidentialCompany(): void
    {
        $record = $this->verify(self::view(FixtureCompanyRegistry::CONFIDENTIAL_SIREN, null));

        self::assertSame('confidential', $record->companyOutcome);
        self::assertNull($record->registryName);
    }

    public function testAProfileWithoutVatIsNotApplicable(): void
    {
        $vies = $this->createMock(VatNumberVerifierInterface::class);
        $vies->expects(self::never())->method('verify');

        $record = $this->verify(self::view('812345676', null), vies: $vies);

        self::assertNull($record->checkedVatNumber);
        self::assertSame('not_applicable', $record->vatOutcome);
    }

    public function testNothingIsAskedForAClientThatCannotBeVerified(): void
    {
        $registry = $this->createMock(CompanyRegistryInterface::class);
        $registry->expects(self::never())->method('findBySiren');

        $views = [
            null,
            self::view('523456788', null, status: 'archived'),
            self::view(null, null, taxStatus: 'individual'),
            self::view(null, null),
        ];

        foreach ($views as $view) {
            $this->handler($view, $registry, new FixtureVatNumberVerifier())(new VerifyClientTaxProfile(self::CLINIC_ID, self::CLIENT_ID));
        }

        self::assertSame([], $this->dispatched);
    }

    private function verify(
        ClientView $view,
        ?CompanyRegistryInterface $registry = null,
        ?VatNumberVerifierInterface $vies = null,
    ): RecordTaxProfileVerification {
        $this->handler($view, $registry ?? new FixtureCompanyRegistry(), $vies ?? new FixtureVatNumberVerifier())(
            new VerifyClientTaxProfile(self::CLINIC_ID, self::CLIENT_ID),
        );

        self::assertCount(1, $this->dispatched);
        self::assertInstanceOf(RecordTaxProfileVerification::class, $this->dispatched[0]);

        return $this->dispatched[0];
    }

    private function handler(?ClientView $view, CompanyRegistryInterface $registry, VatNumberVerifierInterface $vies): VerifyClientTaxProfileHandler
    {
        $clients = $this->createStub(ClientReadRepositoryInterface::class);
        $clients->method('findById')->willReturn($view);

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-10-19 10:00:00', new \DateTimeZone('UTC')));

        $bus = $this->createStub(CommandBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(function (object $command): null {
            $this->dispatched[] = $command;

            return null;
        });

        return new VerifyClientTaxProfileHandler($clients, $registry, $vies, $bus, $clock);
    }

    private static function view(?string $siren, ?string $vatNumber, string $status = 'active', string $taxStatus = 'french_business'): ClientView
    {
        return new ClientView(
            id: self::CLIENT_ID,
            clinicId: self::CLINIC_ID,
            firstName: 'Claire',
            lastName: 'Rostand',
            status: $status,
            contactMethods: [],
            postalAddress: null,
            createdAt: '2026-10-01T10:00:00+00:00',
            updatedAt: '2026-10-01T10:00:00+00:00',
            taxStatus: $taxStatus,
            legalName: 'Élevage des Calanques',
            siren: $siren,
            vatNumber: $vatNumber,
        );
    }
}
