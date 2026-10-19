<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Domain;

use App\Context\Client\Domain\Client;
use App\Context\Client\Domain\Event\ClientCreated;
use App\Context\Client\Domain\Event\ClientIdentityUpdated;
use App\Context\Client\Domain\Event\ClientTaxIdentifiersChanged;
use App\Context\Client\Domain\Event\ClientTaxProfileUpdated;
use App\Context\Client\Domain\Exception\ClientArchivedCannotBeModifiedException;
use App\Context\Client\Domain\ValueObject\ClientId;
use App\Context\Client\Domain\ValueObject\ClientIdentity;
use App\Context\Client\Domain\ValueObject\ClientStatus;
use App\Context\Client\Domain\ValueObject\ClientTaxProfile;
use App\Context\Client\Domain\ValueObject\ClientTaxStatus;
use App\Context\Client\Domain\ValueObject\ClinicId;
use App\Context\Client\Domain\ValueObject\ContactLabel;
use App\Context\Client\Domain\ValueObject\ContactMethod;
use App\Shared\Domain\ValueObject\EmailAddress;
use App\Shared\Domain\ValueObject\FrenchVatNumber;
use App\Shared\Domain\ValueObject\Siren;
use PHPUnit\Framework\TestCase;

/**
 * The client's tax profile: legal name, SIREN and VAT number of a structure.
 */
final class ClientTaxProfileUpdateTest extends TestCase
{
    private const string CLIENT_ID = '01234567-89ab-cdef-0123-456789abcdef';
    private const string CLINIC_ID = '12345678-9abc-def0-1234-56789abcdef0';

    public function testAPlainClientIsAnIndividualAndItsCreationCarriesNoLegalName(): void
    {
        $client = $this->create();
        $events = $client->pullDomainEvents();

        self::assertTrue($client->taxProfile()->equals(ClientTaxProfile::default()));
        self::assertCount(1, $events);
        self::assertInstanceOf(ClientCreated::class, $events[0]);
        self::assertNull($events[0]->payload()['legalName']);
    }

    public function testAStructureIsCreatedWithItsProfileAndItsIdentifiersToVerify(): void
    {
        $client = $this->create($this->calanques());
        $events = $client->pullDomainEvents();

        self::assertSame('Élevage des Calanques', $client->taxProfile()->legalName());
        self::assertCount(2, $events);
        self::assertInstanceOf(ClientCreated::class, $events[0]);
        self::assertInstanceOf(ClientTaxIdentifiersChanged::class, $events[1]);
        self::assertSame('Élevage des Calanques', $events[0]->payload()['legalName']);
    }

    public function testUpdatingTheProfileRecordsAnEvent(): void
    {
        $client = $this->create();
        $_      = $client->pullDomainEvents();
        $now    = new \DateTimeImmutable('2026-10-17 11:00:00');

        $client->updateTaxProfile($this->calanques(), $now);

        self::assertSame(ClientTaxStatus::FRENCH_BUSINESS, $client->taxProfile()->status());
        self::assertSame($now, $client->updatedAt());

        $events = $client->pullDomainEvents();
        self::assertCount(2, $events);
        self::assertInstanceOf(ClientTaxProfileUpdated::class, $events[0]);
        self::assertInstanceOf(ClientTaxIdentifiersChanged::class, $events[1]);
        self::assertSame(self::CLIENT_ID, $events[0]->aggregateId());
        self::assertSame([
            'clientId'  => self::CLIENT_ID,
            'clinicId'  => self::CLINIC_ID,
            'status'    => 'french_business',
            'legalName' => 'Élevage des Calanques',
            'siren'     => '523456788',
            'vatNumber' => 'FR31523456788',
            'firstName' => 'Claire',
            'lastName'  => 'Rostand',
        ], $events[0]->payload());
    }

    public function testAnUnchangedProfileRecordsNothing(): void
    {
        $client = $this->create($this->calanques());
        $_      = $client->pullDomainEvents();
        $before = $client->updatedAt();

        $client->updateTaxProfile($this->calanques(), new \DateTimeImmutable('2026-10-17 11:00:00'));

        self::assertSame([], $client->pullDomainEvents());
        self::assertSame($before, $client->updatedAt());
    }

    public function testAnArchivedClientsProfileCannotChange(): void
    {
        $client = $this->create();
        $client->archive(new \DateTimeImmutable('2026-10-17 10:30:00'));

        $this->expectException(ClientArchivedCannotBeModifiedException::class);

        $client->updateTaxProfile($this->calanques(), new \DateTimeImmutable('2026-10-17 11:00:00'));
    }

    public function testAnIdentityUpdateCarriesTheCurrentLegalName(): void
    {
        $client = $this->create($this->calanques());
        $_      = $client->pullDomainEvents();

        $client->updateIdentity(new ClientIdentity('Claire', 'Rostand-Vidal'), new \DateTimeImmutable('2026-10-17 11:00:00'));

        $events = $client->pullDomainEvents();
        self::assertInstanceOf(ClientIdentityUpdated::class, $events[0]);
        self::assertSame('Élevage des Calanques', $events[0]->payload()['legalName']);
    }

    public function testReconstituteRestoresTheProfileOrDefaultsToIndividual(): void
    {
        $profile = $this->calanques();

        self::assertSame($profile, $this->reconstitute($profile)->taxProfile());
        self::assertTrue($this->reconstitute(null)->taxProfile()->equals(ClientTaxProfile::default()));
    }

    private function calanques(): ClientTaxProfile
    {
        $siren = Siren::fromInput('523456788');

        return ClientTaxProfile::frenchBusiness('Élevage des Calanques', $siren, FrenchVatNumber::forSiren($siren));
    }

    private function create(?ClientTaxProfile $profile = null): Client
    {
        return Client::create(
            ClientId::fromString(self::CLIENT_ID),
            ClinicId::fromString(self::CLINIC_ID),
            new ClientIdentity('Claire', 'Rostand'),
            [ContactMethod::email(EmailAddress::fromString('claire@calanques.fr'), ContactLabel::WORK, true)],
            new \DateTimeImmutable('2026-10-17 10:00:00'),
            $profile,
        );
    }

    private function reconstitute(?ClientTaxProfile $profile): Client
    {
        return Client::reconstitute(
            id: ClientId::fromString(self::CLIENT_ID),
            clinicId: ClinicId::fromString(self::CLINIC_ID),
            identity: new ClientIdentity('Claire', 'Rostand'),
            status: ClientStatus::ACTIVE,
            contactMethods: [ContactMethod::email(EmailAddress::fromString('claire@calanques.fr'), ContactLabel::WORK, true)],
            createdAt: new \DateTimeImmutable('2026-10-17 10:00:00'),
            updatedAt: new \DateTimeImmutable('2026-10-17 10:00:00'),
            taxProfile: $profile,
        );
    }
}
