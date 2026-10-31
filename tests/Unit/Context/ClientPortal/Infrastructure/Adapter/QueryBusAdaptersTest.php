<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Infrastructure\Adapter;

use App\Context\Animal\Application\Query\ListAnimalSummariesPerClientIds\AnimalSummary;
use App\Context\Animal\Application\Query\ListAnimalSummariesPerClientIds\ListAnimalSummariesPerClientIds;
use App\Context\Client\Application\Query\GetClientById\ClientView;
use App\Context\Client\Application\Query\GetClientById\GetClientById;
use App\Context\Client\Application\Query\GetClientById\PostalAddressDto;
use App\Context\Client\Application\Query\GetClientNotificationContact\ClientNotificationContact;
use App\Context\Client\Application\Query\GetClientNotificationContact\GetClientNotificationContact;
use App\Context\Client\Application\Query\IsPortalMobileNumber\IsPortalMobileNumber;
use App\Context\ClientPortal\Infrastructure\Adapter\Animal\QueryBusPetNames;
use App\Context\ClientPortal\Infrastructure\Adapter\Client\QueryBusClientDirectory;
use App\Context\ClientPortal\Infrastructure\Adapter\Client\QueryBusMobilePhonePolicy;
use App\Context\ClientPortal\Infrastructure\Adapter\Clinic\QueryBusClinicNames;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\ClinicDto;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\GetClinic;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\LetterheadDto;
use App\Context\Clinic\Domain\ValueObject\ClinicStatus;
use App\Tests\Support\AnsweringQueryBus;
use PHPUnit\Framework\TestCase;

final class QueryBusAdaptersTest extends TestCase
{
    public function testTheClientContactAndSnapshotAreRead(): void
    {
        $contact = new ClientNotificationContact('client-1', 'Sophie Martin', 'Mme', 'Sophie', 'Martin', 's@example.com', '+33612345678', null, [], true);
        $view    = new ClientView('client-1', 'clinic-1', 'Sophie', 'Martin', 'active', [], new PostalAddressDto('12 rue des Lilas', 'Lyon', 'fr', ' ', '69003'), 'c', 'u');
        $bus     = new AnsweringQueryBus([GetClientNotificationContact::class => $contact, GetClientById::class => $view]);
        $clients = new QueryBusClientDirectory($bus);

        $found = $clients->contactOf('clinic-1', 'client-1');
        self::assertNotNull($found);
        self::assertSame(['client-1', 'Sophie Martin', 'Sophie', 's@example.com', '+33612345678', true], [$found->clientId, $found->displayName, $found->firstName, $found->email, $found->mobile, $found->archived]);

        $snapshot = $clients->snapshotOf('clinic-1', 'client-1');
        self::assertNotNull($snapshot);
        self::assertSame(['Sophie', 'Martin', '12 rue des Lilas', null, '69003', 'Lyon', 'FR'], [
            $snapshot->firstName, $snapshot->lastName, $snapshot->address?->streetLine1, $snapshot->address?->streetLine2, $snapshot->address?->postalCode, $snapshot->address?->city, $snapshot->address?->countryCode,
        ]);

        $query = $bus->asked[0];
        self::assertInstanceOf(GetClientNotificationContact::class, $query);
        self::assertSame(['clinic-1', 'client-1'], [$query->clinicId, $query->clientId]);

        $bus->answer(GetClientById::class, new ClientView('client-1', 'clinic-1', 'Paul', 'Durand', 'active', [], null, 'c', 'u'));
        self::assertNull($clients->snapshotOf('clinic-1', 'client-1')?->address);

        $unknown = new QueryBusClientDirectory(new AnsweringQueryBus());
        self::assertNull($unknown->contactOf('clinic-1', 'client-1'));
        self::assertNull($unknown->snapshotOf('clinic-1', 'client-1'));
    }

    public function testThePhoneRuleIsClients(): void
    {
        $bus = new AnsweringQueryBus([IsPortalMobileNumber::class => static fn (IsPortalMobileNumber $q): bool => '+33612345678' === $q->phone]);

        self::assertTrue(new QueryBusMobilePhonePolicy($bus)->isPortalMobile('+33612345678'));
        self::assertFalse(new QueryBusMobilePhonePolicy($bus)->isPortalMobile('+447700900123'));
        self::assertFalse(new QueryBusMobilePhonePolicy(new AnsweringQueryBus())->isPortalMobile('+33612345678'));
    }

    public function testTheClinicNameAndCityAreRead(): void
    {
        $clinic = static function (?string $city): ClinicDto {
            $letterhead = null === $city ? null : new LetterheadDto(null, null, null, null, null, null, null, null, null, [], $city, null, null, null);

            return new ClinicDto('clinic-1', 'Clinique des Lilas', 'lilas', 'Europe/Paris', 'fr-FR', 'FR', null, 'EUR', ClinicStatus::ACTIVE, null, 'c', 'u', $letterhead);
        };

        $label = new QueryBusClinicNames(new AnsweringQueryBus([GetClinic::class => $clinic('Lyon')]))->clinicOf('clinic-1');
        self::assertSame(['Clinique des Lilas', 'Lyon', 'Europe/Paris'], [$label->name, $label->city, $label->timeZone]);

        self::assertNull(new QueryBusClinicNames(new AnsweringQueryBus([GetClinic::class => $clinic(' ')]))->clinicOf('clinic-1')->city);
        self::assertNull(new QueryBusClinicNames(new AnsweringQueryBus([GetClinic::class => $clinic(null)]))->clinicOf('clinic-1')->city);

        $unknown = new QueryBusClinicNames(new AnsweringQueryBus())->clinicOf('clinic-1');
        self::assertSame(['', null, 'UTC'], [$unknown->name, $unknown->city, $unknown->timeZone]);
    }

    public function testThePetNamesOfTheClientAreRead(): void
    {
        $bus = new AnsweringQueryBus([ListAnimalSummariesPerClientIds::class => ['client-1' => [new AnimalSummary('a1', 'Moka', 'cat'), new AnimalSummary('a2', 'Rex', 'dog')]]]);

        self::assertSame(['Moka', 'Rex'], new QueryBusPetNames($bus)->petNamesOf('clinic-1', 'client-1'));
        $query = $bus->asked[0];
        self::assertInstanceOf(ListAnimalSummariesPerClientIds::class, $query);
        self::assertSame(['clinic-1', ['client-1'], 50], [$query->clinicId, $query->clientIds, $query->limit]);

        self::assertSame([], new QueryBusPetNames($bus)->petNamesOf('clinic-1', 'client-2'));
        self::assertSame([], new QueryBusPetNames(new AnsweringQueryBus())->petNamesOf('clinic-1', 'client-1'));
    }
}
