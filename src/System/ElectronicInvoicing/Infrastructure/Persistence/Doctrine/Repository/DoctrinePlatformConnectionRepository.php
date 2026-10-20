<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Infrastructure\Persistence\Doctrine\Repository;

use App\Shared\Domain\ValueObject\Siren;
use App\System\ElectronicInvoicing\Domain\Connection\OnboardingState;
use App\System\ElectronicInvoicing\Domain\Connection\PlatformConnection;
use App\System\ElectronicInvoicing\Domain\Connection\PlatformConnectionId;
use App\System\ElectronicInvoicing\Domain\Connection\PlatformConnectionRepositoryInterface;
use App\System\ElectronicInvoicing\Domain\Exception\PlatformConnectionNotFoundException;
use App\System\ElectronicInvoicing\Infrastructure\Persistence\Doctrine\Entity\PlatformConnectionEntity;
use App\System\ElectronicInvoicing\Infrastructure\Persistence\Doctrine\Mapper\PlatformConnectionMapper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrinePlatformConnectionRepository implements PlatformConnectionRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private PlatformConnectionMapper $mapper,
    ) {
    }

    public function save(PlatformConnection $connection): void
    {
        $existing = $this->em->find(PlatformConnectionEntity::class, Uuid::fromString($connection->id()->toString()));

        if (null === $existing) {
            $this->em->persist($this->mapper->toEntity($connection));
        } else {
            $this->mapper->toEntity($connection, $existing);
        }

        $this->em->flush();
    }

    public function get(PlatformConnectionId $id): PlatformConnection
    {
        $entity = $this->em->find(PlatformConnectionEntity::class, Uuid::fromString($id->toString()));

        if (null === $entity) {
            throw new PlatformConnectionNotFoundException($id->toString());
        }

        return $this->mapper->toDomain($entity);
    }

    public function byOrganisation(Siren $organisationId): ?PlatformConnection
    {
        $entity = $this->em->getRepository(PlatformConnectionEntity::class)->findOneBy(['organisationId' => $organisationId->value()]);

        return null === $entity ? null : $this->mapper->toDomain($entity);
    }

    public function polled(): array
    {
        $entities = $this->em->getRepository(PlatformConnectionEntity::class)->findBy(['state' => OnboardingState::VERIFIED->value], ['openedAt' => 'ASC']);

        return array_map(fn (PlatformConnectionEntity $e): PlatformConnection => $this->mapper->toDomain($e), $entities);
    }
}
