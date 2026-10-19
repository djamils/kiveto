<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Domain;

use App\Context\Client\Domain\Client;
use App\Context\Client\Domain\Event\ClientTaxProfileVerified;
use App\Context\Client\Domain\TaxProfile\CompanyCheck;
use App\Context\Client\Domain\TaxProfile\CompanyCheckOutcome;
use App\Context\Client\Domain\TaxProfile\TaxProfileVerification;
use App\Context\Client\Domain\TaxProfile\VatCheck;
use App\Context\Client\Domain\TaxProfile\VatCheckOutcome;
use App\Context\Client\Domain\ValueObject\ClientId;
use App\Context\Client\Domain\ValueObject\ClientIdentity;
use App\Context\Client\Domain\ValueObject\ClientStatus;
use App\Context\Client\Domain\ValueObject\ClientTaxProfile;
use App\Context\Client\Domain\ValueObject\ClinicId;
use App\Context\Client\Domain\ValueObject\ContactLabel;
use App\Context\Client\Domain\ValueObject\ContactMethod;
use App\Shared\Domain\ValueObject\EmailAddress;
use App\Shared\Domain\ValueObject\FrenchVatNumber;
use App\Shared\Domain\ValueObject\Siren;
use PHPUnit\Framework\TestCase;

/**
 * Storing the answer of the registry and VIES on the client.
 */
final class ClientTaxProfileVerificationTest extends TestCase
{
    private const string CLIENT_ID    = '01234567-89ab-cdef-0123-456789abcdef';
    private const string CLINIC_ID    = '12345678-9abc-def0-1234-56789abcdef0';
    private const string UPDATED_AT   = '2026-10-01 10:00:00';
    private const string REQUESTED_AT = '2026-10-19 09:00:00';
    private const string CHECKED_AT   = '2026-10-19 09:00:05';

    public function testAnAnswerForTheCurrentIdentifiersIsStored(): void
    {
        $client = self::client(self::calanques());

        self::assertTrue($client->recordTaxProfileVerification('523456788', 'FR31523456788', self::answer()));

        $verification = $client->taxProfileVerification();
        self::assertSame(CompanyCheckOutcome::ACTIVE, $verification->company()->outcome());
        self::assertSame('EARL ELEVAGE DES CALANQUES', $verification->company()->registryName());
        self::assertSame(VatCheckOutcome::VALID, $verification->vat()->outcome());
        self::assertNull($verification->requestedAt());
        self::assertSame((new \DateTimeImmutable(self::UPDATED_AT))->format(\DATE_ATOM), $client->updatedAt()->format(\DATE_ATOM));

        $events = $client->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ClientTaxProfileVerified::class, $events[0]);
        self::assertSame([
            'clientId'         => self::CLIENT_ID,
            'clinicId'         => self::CLINIC_ID,
            'companyOutcome'   => 'active',
            'registryName'     => 'EARL ELEVAGE DES CALANQUES',
            'companyCheckedAt' => '2026-10-19T09:00:05+00:00',
            'vatOutcome'       => 'valid',
            'vatCheckedAt'     => '2026-10-19T09:00:05+00:00',
        ], $events[0]->payload());
    }

    public function testAProfileWithoutVatStoresNotApplicable(): void
    {
        $siren  = Siren::fromInput('812345676');
        $client = self::client(ClientTaxProfile::frenchBusiness('Centre équestre du Lac', $siren, null));
        $at     = new \DateTimeImmutable(self::CHECKED_AT);

        self::assertTrue($client->recordTaxProfileVerification('812345676', null, TaxProfileVerification::of(
            CompanyCheck::checked(CompanyCheckOutcome::ACTIVE, 'CENTRE EQUESTRE DU LAC', $at),
            VatCheck::checked(VatCheckOutcome::NOT_APPLICABLE, $at),
        )));

        self::assertSame(VatCheckOutcome::NOT_APPLICABLE, $client->taxProfileVerification()->vat()->outcome());
    }

    public function testALateAnswerForAnOldSirenChangesNothing(): void
    {
        $client = self::client(self::calanques());
        $before = $client->taxProfileVerification();

        self::assertFalse($client->recordTaxProfileVerification('441569738', 'FR31523456788', self::answer()));

        self::assertSame($before, $client->taxProfileVerification());
        self::assertSame((new \DateTimeImmutable(self::REQUESTED_AT))->format(\DATE_ATOM), $client->taxProfileVerification()->requestedAt()?->format(\DATE_ATOM));
        self::assertSame([], $client->pullDomainEvents());
    }

    public function testALateAnswerForAnOldVatNumberChangesNothing(): void
    {
        $client = self::client(self::calanques());

        self::assertFalse($client->recordTaxProfileVerification('523456788', null, self::answer()));

        self::assertSame((new \DateTimeImmutable(self::REQUESTED_AT))->format(\DATE_ATOM), $client->taxProfileVerification()->requestedAt()?->format(\DATE_ATOM));
        self::assertSame([], $client->pullDomainEvents());
    }

    public function testAnArchivedClientStoresNothingAndAwaitsNothing(): void
    {
        $client = self::client(self::calanques(), ClientStatus::ARCHIVED);

        self::assertTrue($client->recordTaxProfileVerification('523456788', 'FR31523456788', self::answer()));

        self::assertTrue($client->taxProfileVerification()->equals(TaxProfileVerification::unchecked()));
        self::assertSame([], $client->pullDomainEvents());
    }

    public function testAnIndividualStoresNothingAndAwaitsNothing(): void
    {
        $client = self::client(ClientTaxProfile::individual('Refuge des Collines'));

        self::assertTrue($client->recordTaxProfileVerification('523456788', null, self::answer()));

        self::assertNull($client->taxProfileVerification()->requestedAt());
        self::assertSame(CompanyCheckOutcome::UNCHECKED, $client->taxProfileVerification()->company()->outcome());
        self::assertSame([], $client->pullDomainEvents());
    }

    public function testAnUnverifiableClientAwaitingNothingDoesNotChange(): void
    {
        $client = self::client(ClientTaxProfile::individual(null), ClientStatus::ACTIVE, null);

        self::assertFalse($client->recordTaxProfileVerification('523456788', null, self::answer()));
    }

    private static function answer(): TaxProfileVerification
    {
        $at = new \DateTimeImmutable(self::CHECKED_AT);

        return TaxProfileVerification::of(
            CompanyCheck::checked(CompanyCheckOutcome::ACTIVE, 'EARL ELEVAGE DES CALANQUES', $at),
            VatCheck::checked(VatCheckOutcome::VALID, $at),
            $at,
        );
    }

    private static function calanques(): ClientTaxProfile
    {
        $siren = Siren::fromInput('523456788');

        return ClientTaxProfile::frenchBusiness('Élevage des Calanques', $siren, FrenchVatNumber::forSiren($siren));
    }

    private static function client(
        ClientTaxProfile $profile,
        ClientStatus $status = ClientStatus::ACTIVE,
        ?string $requestedAt = self::REQUESTED_AT,
    ): Client {
        return Client::reconstitute(
            id: ClientId::fromString(self::CLIENT_ID),
            clinicId: ClinicId::fromString(self::CLINIC_ID),
            identity: new ClientIdentity('Claire', 'Rostand'),
            status: $status,
            contactMethods: [ContactMethod::email(EmailAddress::fromString('claire@calanques.fr'), ContactLabel::WORK, true)],
            createdAt: new \DateTimeImmutable(self::UPDATED_AT),
            updatedAt: new \DateTimeImmutable(self::UPDATED_AT),
            taxProfile: $profile,
            taxProfileVerification: TaxProfileVerification::unchecked(null === $requestedAt ? null : new \DateTimeImmutable($requestedAt)),
        );
    }
}
