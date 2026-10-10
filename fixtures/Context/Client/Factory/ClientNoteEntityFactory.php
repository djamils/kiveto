<?php

declare(strict_types=1);

namespace App\Fixtures\Context\Client\Factory;

use App\Context\Client\Domain\ValueObject\ClientNoteKind;
use App\Context\Client\Infrastructure\Persistence\Doctrine\Entity\ClientNoteEntity;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<ClientNoteEntity>
 */
final class ClientNoteEntityFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return ClientNoteEntity::class;
    }

    public function forClient(string $clinicId, string $clientId): self
    {
        return $this->with([
            'clinicId' => Uuid::fromString($clinicId),
            'clientId' => Uuid::fromString($clientId),
        ]);
    }

    public function attention(): self
    {
        return $this->with(['kind' => ClientNoteKind::ATTENTION]);
    }

    public function writtenBy(string $authorName, string $createdAt): self
    {
        return $this->with([
            'authorName' => $authorName,
            'createdAt'  => new \DateTimeImmutable($createdAt),
        ]);
    }

    protected function defaults(): array|callable
    {
        return [
            'id'         => Uuid::v7(),
            'clinicId'   => Uuid::v7(),
            'clientId'   => Uuid::v7(),
            'kind'       => ClientNoteKind::INFORMATION,
            'content'    => self::faker()->sentence(),
            'authorName' => self::faker()->name(),
            'createdAt'  => \DateTimeImmutable::createFromMutable(self::faker()->dateTimeBetween('-6 months')),
        ];
    }
}
