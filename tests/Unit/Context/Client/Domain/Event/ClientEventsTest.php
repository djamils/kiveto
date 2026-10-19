<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Domain\Event;

use App\Context\Client\Domain\Event\ClientArchived;
use App\Context\Client\Domain\Event\ClientArchivedIntegrationEvent;
use App\Context\Client\Domain\Event\ClientContactMethodsReplaced;
use App\Context\Client\Domain\Event\ClientCreated;
use App\Context\Client\Domain\Event\ClientIdentityUpdated;
use App\Context\Client\Domain\Event\ClientPostalAddressUpdated;
use App\Context\Client\Domain\Event\ClientTaxIdentifiersChanged;
use App\Context\Client\Domain\Event\ClientTaxProfileVerified;
use App\Context\Client\Domain\Event\ClientUnarchived;
use App\Shared\Domain\Event\DomainEventInterface;
use App\Shared\Domain\Event\IntegrationEventInterface;
use PHPUnit\Framework\TestCase;

final class ClientEventsTest extends TestCase
{
    public function testClientCreatedImplementsDomainEventInterface(): void
    {
        $contactMethods = [
            ['type' => 'email', 'label' => 'work', 'value' => 'test@example.com', 'isPrimary' => true],
        ];
        $event = new ClientCreated(
            'client-123',
            'clinic-456',
            'John',
            'Doe',
            $contactMethods
        );

        self::assertInstanceOf(DomainEventInterface::class, $event);
    }

    public function testClientCreatedReturnsCorrectAggregateId(): void
    {
        $contactMethods = [
            ['type' => 'email', 'label' => 'work', 'value' => 'test@example.com', 'isPrimary' => true],
        ];
        $event = new ClientCreated(
            'client-123',
            'clinic-456',
            'John',
            'Doe',
            $contactMethods
        );

        self::assertSame('client-123', $event->aggregateId());
    }

    public function testClientCreatedReturnsCorrectPayload(): void
    {
        $contactMethods = [
            ['type' => 'email', 'label' => 'work', 'value' => 'test@example.com', 'isPrimary' => true],
        ];
        $event = new ClientCreated(
            'client-123',
            'clinic-456',
            'John',
            'Doe',
            $contactMethods
        );

        $payload = $event->payload();

        self::assertSame('client-123', $payload['clientId']);
        self::assertSame('clinic-456', $payload['clinicId']);
        self::assertSame('John', $payload['firstName']);
        self::assertSame('Doe', $payload['lastName']);
        self::assertSame($contactMethods, $payload['contactMethods']);
    }

    public function testClientCreatedReturnsCorrectName(): void
    {
        $contactMethods = [
            ['type' => 'email', 'label' => 'work', 'value' => 'test@example.com', 'isPrimary' => true],
        ];
        $event = new ClientCreated(
            'client-123',
            'clinic-456',
            'John',
            'Doe',
            $contactMethods
        );

        self::assertSame('client.client.created.v1', $event->name());
    }

    public function testClientArchivedImplementsDomainEventInterface(): void
    {
        $event = new ClientArchived('client-123', 'clinic-456');

        self::assertInstanceOf(DomainEventInterface::class, $event);
    }

    public function testClientArchivedReturnsCorrectData(): void
    {
        $event = new ClientArchived('client-123', 'clinic-456');

        self::assertSame('client-123', $event->aggregateId());
        self::assertSame('client.client.archived.v1', $event->name());

        $payload = $event->payload();
        self::assertSame('client-123', $payload['clientId']);
        self::assertSame('clinic-456', $payload['clinicId']);
    }

    public function testClientUnarchivedImplementsDomainEventInterface(): void
    {
        $event = new ClientUnarchived('client-123', 'clinic-456');

        self::assertInstanceOf(DomainEventInterface::class, $event);
    }

    public function testClientUnarchivedReturnsCorrectData(): void
    {
        $event = new ClientUnarchived('client-123', 'clinic-456');

        self::assertSame('client-123', $event->aggregateId());
        self::assertSame('client.client.unarchived.v1', $event->name());

        $payload = $event->payload();
        self::assertSame('client-123', $payload['clientId']);
        self::assertSame('clinic-456', $payload['clinicId']);
    }

    public function testClientIdentityUpdatedImplementsDomainEventInterface(): void
    {
        $event = new ClientIdentityUpdated('client-123', 'clinic-456', 'Jane', 'Smith');

        self::assertInstanceOf(DomainEventInterface::class, $event);
    }

    public function testClientIdentityUpdatedReturnsCorrectData(): void
    {
        $event = new ClientIdentityUpdated('client-123', 'clinic-456', 'Jane', 'Smith');

        self::assertSame('client-123', $event->aggregateId());
        self::assertSame('client.client-identity.updated.v1', $event->name());

        $payload = $event->payload();
        self::assertSame('client-123', $payload['clientId']);
        self::assertSame('clinic-456', $payload['clinicId']);
        self::assertSame('Jane', $payload['firstName']);
        self::assertSame('Smith', $payload['lastName']);
        self::assertSame('unspecified', $payload['civility']);
    }

    public function testClientPostalAddressUpdatedImplementsDomainEventInterface(): void
    {
        $postalAddress = [
            'streetLine1' => '123 Main St',
            'streetLine2' => null,
            'postalCode'  => '75001',
            'city'        => 'Paris',
            'region'      => null,
            'countryCode' => 'FR',
        ];
        $event = new ClientPostalAddressUpdated('client-123', 'clinic-456', $postalAddress);

        self::assertInstanceOf(DomainEventInterface::class, $event);
    }

    public function testClientPostalAddressUpdatedReturnsCorrectData(): void
    {
        $postalAddress = [
            'streetLine1' => '123 Main St',
            'streetLine2' => null,
            'postalCode'  => '75001',
            'city'        => 'Paris',
            'region'      => null,
            'countryCode' => 'FR',
        ];
        $event = new ClientPostalAddressUpdated('client-123', 'clinic-456', $postalAddress);

        self::assertSame('client-123', $event->aggregateId());
        self::assertSame('client.client-postal-address.updated.v1', $event->name());

        $payload = $event->payload();
        self::assertSame('client-123', $payload['clientId']);
        self::assertSame('clinic-456', $payload['clinicId']);
        self::assertSame($postalAddress, $payload['postalAddress']);
    }

    public function testClientPostalAddressUpdatedWithNullAddress(): void
    {
        $event = new ClientPostalAddressUpdated('client-123', 'clinic-456', null);

        $payload = $event->payload();
        self::assertNull($payload['postalAddress']);
    }

    public function testClientContactMethodsReplacedImplementsDomainEventInterface(): void
    {
        $contactMethods = [
            ['type' => 'email', 'label' => 'work', 'value' => 'test@example.com', 'isPrimary' => true],
            ['type' => 'phone', 'label' => 'mobile', 'value' => '+33612345678', 'isPrimary' => true],
        ];
        $event = new ClientContactMethodsReplaced('client-123', 'clinic-456', $contactMethods);

        self::assertInstanceOf(DomainEventInterface::class, $event);
    }

    public function testClientContactMethodsReplacedReturnsCorrectData(): void
    {
        $contactMethods = [
            ['type' => 'email', 'label' => 'work', 'value' => 'test@example.com', 'isPrimary' => true],
            ['type' => 'phone', 'label' => 'mobile', 'value' => '+33612345678', 'isPrimary' => true],
        ];
        $event = new ClientContactMethodsReplaced('client-123', 'clinic-456', $contactMethods);

        self::assertSame('client-123', $event->aggregateId());
        self::assertSame('client.client-contact-methods.replaced.v1', $event->name());

        $payload = $event->payload();
        self::assertSame('client-123', $payload['clientId']);
        self::assertSame('clinic-456', $payload['clinicId']);
        self::assertSame($contactMethods, $payload['contactMethods']);
    }

    public function testClientArchivedIntegrationEventImplementsIntegrationEventInterface(): void
    {
        $event = new ClientArchivedIntegrationEvent('client-123', 'clinic-456');

        self::assertInstanceOf(IntegrationEventInterface::class, $event);
    }

    public function testClientArchivedIntegrationEventReturnsCorrectData(): void
    {
        $event = new ClientArchivedIntegrationEvent('client-123', 'clinic-456');

        self::assertSame('client-123', $event->aggregateId());
        self::assertSame('client.client-archived-integration.event.v1', $event->name());

        $payload = $event->payload();
        self::assertSame('client-123', $payload['clientId']);
        self::assertSame('clinic-456', $payload['clinicId']);
    }

    public function testClientTaxIdentifiersChangedReturnsCorrectData(): void
    {
        $event = new ClientTaxIdentifiersChanged('client-123', 'clinic-456', 'french_business', '441569738', '523456788', null, 'FR31523456788');

        self::assertInstanceOf(DomainEventInterface::class, $event);
        self::assertSame('client-123', $event->aggregateId());
        self::assertSame('client.client-tax-identifiers.changed.v1', $event->name());
        self::assertSame([
            'clientId'          => 'client-123',
            'clinicId'          => 'clinic-456',
            'taxStatus'         => 'french_business',
            'previousSiren'     => '441569738',
            'siren'             => '523456788',
            'previousVatNumber' => null,
            'vatNumber'         => 'FR31523456788',
        ], $event->payload());
    }

    public function testClientTaxProfileVerifiedReturnsCorrectData(): void
    {
        $event = new ClientTaxProfileVerified('client-123', 'clinic-456', 'active', 'EARL ELEVAGE DES CALANQUES', '2026-10-18T08:12:00+00:00', 'valid', '2026-10-18T08:12:00+00:00');

        self::assertInstanceOf(DomainEventInterface::class, $event);
        self::assertSame('client-123', $event->aggregateId());
        self::assertSame('client.client-tax-profile.verified.v1', $event->name());
        self::assertSame([
            'clientId'         => 'client-123',
            'clinicId'         => 'clinic-456',
            'companyOutcome'   => 'active',
            'registryName'     => 'EARL ELEVAGE DES CALANQUES',
            'companyCheckedAt' => '2026-10-18T08:12:00+00:00',
            'vatOutcome'       => 'valid',
            'vatCheckedAt'     => '2026-10-18T08:12:00+00:00',
        ], $event->payload());
    }
}
