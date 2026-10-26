<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Client\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Client\Application\Port\ClientCommunicationReadRepositoryInterface;
use App\Context\Client\Domain\ClientCommunication;
use App\Context\Client\Domain\Repository\ClientCommunicationRepositoryInterface;
use App\Context\Client\Domain\ValueObject\ClientCommunicationId;
use App\Context\Client\Domain\ValueObject\ClientId;
use App\Context\Client\Domain\ValueObject\ClinicId;
use App\Context\Client\Domain\ValueObject\CommunicationDirection;
use App\Context\Client\Domain\ValueObject\ContactChannel;
use App\Context\Client\Domain\ValueObject\DeliveryStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

final class DoctrineClientCommunicationRepositoryTest extends KernelTestCase
{
    use Factories;

    private const string CLIENT_ID = '01234567-89ab-cdef-0123-456789abcdef';
    private const string CLINIC_ID = '12345678-9abc-def0-1234-56789abcdef0';

    public function testListsTheCommunicationsOfOneClientNewestFirst(): void
    {
        $this->save('0192a000-0000-7000-8000-000000000001', self::CLINIC_ID, self::CLIENT_ID, 'Appel entrant', 'Prise de rendez-vous.', '2026-03-02 09:12:00');
        $this->save('0192a000-0000-7000-8000-000000000002', self::CLINIC_ID, self::CLIENT_ID, 'Relance', null, '2026-07-22 10:00:00');
        // Another client and another clinic must not leak into the list.
        $this->save('0192a000-0000-7000-8000-000000000003', self::CLINIC_ID, '99999999-9999-7999-8999-999999999999', 'Autre client', null, '2026-09-01 09:00:00');
        $this->save('0192a000-0000-7000-8000-000000000004', '88888888-8888-7888-8888-888888888888', self::CLIENT_ID, 'Autre clinique', null, '2026-09-02 09:00:00');

        $communications = $this->readRepository()->listForClient(
            ClinicId::fromString(self::CLINIC_ID),
            ClientId::fromString(self::CLIENT_ID),
        );

        self::assertCount(2, $communications);

        self::assertSame('0192a000-0000-7000-8000-000000000002', $communications[0]->id);
        self::assertSame('Relance', $communications[0]->subject);
        self::assertNull($communications[0]->body);

        self::assertSame('0192a000-0000-7000-8000-000000000001', $communications[1]->id);
        self::assertSame('phone', $communications[1]->channel);
        self::assertSame('inbound', $communications[1]->direction);
        self::assertSame('Appel entrant', $communications[1]->subject);
        self::assertSame('Prise de rendez-vous.', $communications[1]->body);
        self::assertSame('Camille', $communications[1]->authorName);
        self::assertFalse($communications[1]->automated);
        self::assertNull($communications[1]->deliveryStatus);
        self::assertNull($communications[1]->deliveryStatusLabel);
        self::assertNull($communications[1]->statusChangedAt);
        self::assertSame('2026-03-02 09:12:00', $communications[1]->occurredAt->format('Y-m-d H:i:s'));
    }

    public function testReturnsAnEmptyListWhenNothingWasLogged(): void
    {
        self::assertSame([], $this->readRepository()->listForClient(
            ClinicId::fromString(self::CLINIC_ID),
            ClientId::fromString(self::CLIENT_ID),
        ));
    }

    public function testAnAutomatedEntryIsFoundByItsMessageAndUpdatesItsStatus(): void
    {
        $repository = $this->repository();
        $messageId  = '0192a000-0000-7000-8000-0000000000e1';

        $repository->save(ClientCommunication::recordAutomated(
            ClientCommunicationId::fromString('0192a000-0000-7000-8000-000000000005'),
            ClinicId::fromString(self::CLINIC_ID),
            ClientId::fromString(self::CLIENT_ID),
            ContactChannel::EMAIL,
            'Rendez-vous confirmé — Milo',
            'À h.lambert@example.com',
            $messageId,
            DeliveryStatus::SENT,
            2,
            new \DateTimeImmutable('2026-10-26 09:42:05'),
        ));
        $this->entityManager()->clear();

        self::assertNull($repository->findByNotificationMessageId('0192a000-0000-7000-8000-0000000000e2'));

        $entry = $repository->findByNotificationMessageId($messageId);
        self::assertInstanceOf(ClientCommunication::class, $entry);
        self::assertSame(DeliveryStatus::SENT, $entry->deliveryStatus());

        $entry->recordDeliveryStatus(DeliveryStatus::DELIVERED, 3, new \DateTimeImmutable('2026-10-26 09:42:30'));
        $repository->save($entry);
        $this->entityManager()->clear();

        $reloaded = $repository->findByNotificationMessageId($messageId);
        self::assertSame(DeliveryStatus::DELIVERED, $reloaded?->deliveryStatus());
        self::assertSame(3, $reloaded->deliveryStatusSequence());
        self::assertSame('2026-10-26 09:42:30', $reloaded->statusChangedAt()?->format('Y-m-d H:i:s'));
        self::assertTrue($reloaded->isAutomated());

        $views = $this->readRepository()->listForClient(ClinicId::fromString(self::CLINIC_ID), ClientId::fromString(self::CLIENT_ID));
        self::assertCount(1, $views);
        self::assertTrue($views[0]->automated);
        self::assertSame('DELIVERED', $views[0]->deliveryStatus);
        self::assertSame('Délivré', $views[0]->deliveryStatusLabel);
        self::assertSame('2026-10-26 09:42:30', $views[0]->statusChangedAt?->format('Y-m-d H:i:s'));
        self::assertSame('Envoi automatique', $views[0]->authorName);
    }

    private function repository(): ClientCommunicationRepositoryInterface
    {
        $repository = static::getContainer()->get(ClientCommunicationRepositoryInterface::class);
        \assert($repository instanceof ClientCommunicationRepositoryInterface);

        return $repository;
    }

    private function entityManager(): EntityManagerInterface
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        \assert($em instanceof EntityManagerInterface);

        return $em;
    }

    private function save(
        string $id,
        string $clinicId,
        string $clientId,
        string $subject,
        ?string $body,
        string $occurredAt,
    ): void {
        $repository = static::getContainer()->get(ClientCommunicationRepositoryInterface::class);
        \assert($repository instanceof ClientCommunicationRepositoryInterface);

        $repository->save(ClientCommunication::log(
            ClientCommunicationId::fromString($id),
            ClinicId::fromString($clinicId),
            ClientId::fromString($clientId),
            ContactChannel::PHONE,
            CommunicationDirection::INBOUND,
            $subject,
            $body,
            'Camille',
            new \DateTimeImmutable($occurredAt),
        ));
    }

    private function readRepository(): ClientCommunicationReadRepositoryInterface
    {
        $readRepository = static::getContainer()->get(ClientCommunicationReadRepositoryInterface::class);
        \assert($readRepository instanceof ClientCommunicationReadRepositoryInterface);

        return $readRepository;
    }
}
