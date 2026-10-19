<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Domain;

use App\Context\Client\Domain\Client;
use App\Context\Client\Domain\Event\ClientTaxIdentifiersChanged;
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
 * A new SIREN or VAT number resets the verification and asks for a new one;
 * a legal-name change does not.
 */
final class ClientTaxIdentifiersChangedTest extends TestCase
{
    private const string CLIENT_ID = '01234567-89ab-cdef-0123-456789abcdef';
    private const string CLINIC_ID = '12345678-9abc-def0-1234-56789abcdef0';
    private const string NOW       = '2026-10-19 10:00:00';

    public function testAProfessionalCreatedWithASirenIsToVerify(): void
    {
        $createdAt = new \DateTimeImmutable('2026-10-17 10:00:00');
        $client    = Client::create(
            ClientId::fromString(self::CLIENT_ID),
            ClinicId::fromString(self::CLINIC_ID),
            new ClientIdentity('Claire', 'Rostand'),
            [ContactMethod::email(EmailAddress::fromString('claire@calanques.fr'), ContactLabel::WORK, true)],
            $createdAt,
            self::calanques(),
        );

        $event = self::identifiersEvent($client);
        self::assertNotNull($event);
        self::assertSame([
            'clientId'          => self::CLIENT_ID,
            'clinicId'          => self::CLINIC_ID,
            'taxStatus'         => 'french_business',
            'previousSiren'     => null,
            'siren'             => '523456788',
            'previousVatNumber' => null,
            'vatNumber'         => 'FR31523456788',
        ], $event->payload());
        self::assertSame($createdAt, $client->taxProfileVerification()->requestedAt());
        self::assertSame(CompanyCheckOutcome::UNCHECKED, $client->taxProfileVerification()->company()->outcome());
    }

    public function testAnIndividualCreatedWithoutSirenHasNothingToVerify(): void
    {
        $client = Client::create(
            ClientId::fromString(self::CLIENT_ID),
            ClinicId::fromString(self::CLINIC_ID),
            new ClientIdentity('Claire', 'Rostand'),
            [ContactMethod::email(EmailAddress::fromString('claire@calanques.fr'), ContactLabel::WORK, true)],
            new \DateTimeImmutable('2026-10-17 10:00:00'),
            ClientTaxProfile::individual('Refuge des Collines'),
        );

        self::assertNull(self::identifiersEvent($client));
        self::assertNull($client->taxProfileVerification()->requestedAt());
    }

    public function testASirenChangeResetsAndRequestsTheVerification(): void
    {
        $client = self::verified(self::calanques());
        $siren  = Siren::fromInput('812345676');

        $client->updateTaxProfile(ClientTaxProfile::frenchBusiness('Élevage des Calanques', $siren, null), new \DateTimeImmutable(self::NOW));

        $event = self::identifiersEvent($client);
        self::assertNotNull($event);
        self::assertSame('523456788', $event->payload()['previousSiren']);
        self::assertSame('812345676', $event->payload()['siren']);
        self::assertSame('FR31523456788', $event->payload()['previousVatNumber']);
        self::assertNull($event->payload()['vatNumber']);
        self::assertTrue($client->taxProfileVerification()->equals(TaxProfileVerification::unchecked(new \DateTimeImmutable(self::NOW))));
    }

    public function testAVatChangeResetsAndRequestsTheVerification(): void
    {
        $siren  = Siren::fromInput('523456788');
        $client = self::verified(ClientTaxProfile::frenchBusiness('Élevage des Calanques', $siren, null));

        $client->updateTaxProfile(self::calanques(), new \DateTimeImmutable(self::NOW));

        $event = self::identifiersEvent($client);
        self::assertNotNull($event);
        self::assertNull($event->payload()['previousVatNumber']);
        self::assertSame('FR31523456788', $event->payload()['vatNumber']);
        self::assertSame(VatCheckOutcome::UNCHECKED, $client->taxProfileVerification()->vat()->outcome());
        self::assertTrue($client->taxProfileVerification()->isPending(new \DateTimeImmutable(self::NOW)));
    }

    public function testBecomingAnIndividualResetsWithoutRequesting(): void
    {
        $client = self::verified(self::calanques());

        $client->updateTaxProfile(ClientTaxProfile::individual('Élevage des Calanques'), new \DateTimeImmutable(self::NOW));

        $event = self::identifiersEvent($client);
        self::assertNotNull($event);
        self::assertSame('individual', $event->payload()['taxStatus']);
        self::assertNull($event->payload()['siren']);
        self::assertTrue($client->taxProfileVerification()->equals(TaxProfileVerification::unchecked()));
    }

    public function testALegalNameChangeKeepsTheVerification(): void
    {
        $client = self::verified(self::calanques());
        $before = $client->taxProfileVerification();
        $siren  = Siren::fromInput('523456788');

        $client->updateTaxProfile(
            ClientTaxProfile::frenchBusiness('EARL ELEVAGE DES CALANQUES', $siren, FrenchVatNumber::forSiren($siren)),
            new \DateTimeImmutable(self::NOW),
        );

        self::assertNull(self::identifiersEvent($client));
        self::assertSame($before, $client->taxProfileVerification());
    }

    public function testAnUnchangedProfileKeepsTheVerification(): void
    {
        $client = self::verified(self::calanques());
        $before = $client->taxProfileVerification();

        $client->updateTaxProfile(self::calanques(), new \DateTimeImmutable(self::NOW));

        self::assertSame([], $client->pullDomainEvents());
        self::assertSame($before, $client->taxProfileVerification());
    }

    public function testReconstituteRestoresTheVerificationOrDefaultsToUnchecked(): void
    {
        $verification = self::verified(self::calanques())->taxProfileVerification();

        self::assertSame($verification, self::reconstitute(self::calanques(), $verification)->taxProfileVerification());
        self::assertTrue(self::reconstitute(self::calanques(), null)->taxProfileVerification()->equals(TaxProfileVerification::unchecked()));
    }

    private static function identifiersEvent(Client $client): ?ClientTaxIdentifiersChanged
    {
        foreach ($client->pullDomainEvents() as $event) {
            if ($event instanceof ClientTaxIdentifiersChanged) {
                return $event;
            }
        }

        return null;
    }

    private static function calanques(): ClientTaxProfile
    {
        $siren = Siren::fromInput('523456788');

        return ClientTaxProfile::frenchBusiness('Élevage des Calanques', $siren, FrenchVatNumber::forSiren($siren));
    }

    private static function verified(ClientTaxProfile $profile): Client
    {
        $checkedAt = new \DateTimeImmutable('2026-10-18 08:12:00');

        return self::reconstitute($profile, TaxProfileVerification::of(
            CompanyCheck::checked(CompanyCheckOutcome::ACTIVE, 'EARL ELEVAGE DES CALANQUES', $checkedAt),
            VatCheck::checked(VatCheckOutcome::VALID, $checkedAt),
        ));
    }

    private static function reconstitute(ClientTaxProfile $profile, ?TaxProfileVerification $verification): Client
    {
        return Client::reconstitute(
            id: ClientId::fromString(self::CLIENT_ID),
            clinicId: ClinicId::fromString(self::CLINIC_ID),
            identity: new ClientIdentity('Claire', 'Rostand'),
            status: ClientStatus::ACTIVE,
            contactMethods: [ContactMethod::email(EmailAddress::fromString('claire@calanques.fr'), ContactLabel::WORK, true)],
            createdAt: new \DateTimeImmutable('2026-10-01 10:00:00'),
            updatedAt: new \DateTimeImmutable('2026-10-01 10:00:00'),
            taxProfile: $profile,
            taxProfileVerification: $verification,
        );
    }
}
