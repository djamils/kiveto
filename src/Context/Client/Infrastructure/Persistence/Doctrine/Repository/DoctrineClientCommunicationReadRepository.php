<?php

declare(strict_types=1);

namespace App\Context\Client\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Client\Application\Port\ClientCommunicationReadRepositoryInterface;
use App\Context\Client\Application\Query\ListClientCommunications\ClientCommunicationView;
use App\Context\Client\Domain\ValueObject\ClientId;
use App\Context\Client\Domain\ValueObject\ClinicId;
use App\Context\Client\Infrastructure\Persistence\Doctrine\Entity\ClientCommunicationEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineClientCommunicationReadRepository implements ClientCommunicationReadRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    public function listForClient(ClinicId $clinicId, ClientId $clientId): array
    {
        $entities = $this->em->getRepository(ClientCommunicationEntity::class)->findBy(
            [
                'clinicId' => Uuid::fromString($clinicId->toString()),
                'clientId' => Uuid::fromString($clientId->toString()),
            ],
            ['occurredAt' => 'DESC', 'id' => 'DESC'],
        );

        return array_map(
            static fn (ClientCommunicationEntity $entity): ClientCommunicationView => new ClientCommunicationView(
                id: $entity->id->toString(),
                channel: $entity->channel->value,
                direction: $entity->direction->value,
                subject: $entity->subject,
                body: $entity->body,
                authorName: $entity->authorName,
                occurredAt: $entity->occurredAt,
            ),
            $entities,
        );
    }
}
