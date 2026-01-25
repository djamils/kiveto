<?php

declare(strict_types=1);

namespace App\Tests\Unit\Client\Domain\Event;

use App\Client\Domain\Event\ClientArchived;
use App\Client\Domain\Event\ClientArchivedIntegrationEvent;
use App\Client\Domain\Event\ClientContactMethodsReplaced;
use App\Client\Domain\Event\ClientCreated;
use App\Client\Domain\Event\ClientIdentityUpdated;
use App\Client\Domain\Event\ClientPostalAddressUpdated;
use App\Client\Domain\Event\ClientUnarchived;
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
}
