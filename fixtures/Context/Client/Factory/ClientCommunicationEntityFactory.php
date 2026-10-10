<?php

declare(strict_types=1);

namespace App\Fixtures\Context\Client\Factory;

use App\Context\Client\Domain\ValueObject\CommunicationDirection;
use App\Context\Client\Domain\ValueObject\ContactChannel;
use App\Context\Client\Infrastructure\Persistence\Doctrine\Entity\ClientCommunicationEntity;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<ClientCommunicationEntity>
 */
final class ClientCommunicationEntityFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return ClientCommunicationEntity::class;
    }

    public function forClient(string $clinicId, string $clientId): self
    {
        return $this->with([
            'clinicId' => Uuid::fromString($clinicId),
            'clientId' => Uuid::fromString($clientId),
        ]);
    }

    public function via(ContactChannel $channel, CommunicationDirection $direction): self
    {
        return $this->with(['channel' => $channel, 'direction' => $direction]);
    }

    public function about(string $subject, ?string $body = null): self
    {
        return $this->with(['subject' => $subject, 'body' => $body]);
    }

    public function loggedBy(string $authorName, string $occurredAt): self
    {
        return $this->with([
            'authorName' => $authorName,
            'occurredAt' => new \DateTimeImmutable($occurredAt),
        ]);
    }

    protected function defaults(): array|callable
    {
        return [
            'id'         => Uuid::v7(),
            'clinicId'   => Uuid::v7(),
            'clientId'   => Uuid::v7(),
            'channel'    => ContactChannel::PHONE,
            'direction'  => CommunicationDirection::OUTBOUND,
            'subject'    => self::faker()->sentence(4),
            'body'       => null,
            'authorName' => self::faker()->name(),
            'occurredAt' => \DateTimeImmutable::createFromMutable(self::faker()->dateTimeBetween('-6 months')),
        ];
    }
}
