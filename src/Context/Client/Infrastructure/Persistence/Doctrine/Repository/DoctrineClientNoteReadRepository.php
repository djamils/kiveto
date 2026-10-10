<?php

declare(strict_types=1);

namespace App\Context\Client\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Client\Application\Port\ClientNoteReadRepositoryInterface;
use App\Context\Client\Application\Query\ListClientNotes\ClientNoteView;
use App\Context\Client\Domain\ValueObject\ClientId;
use App\Context\Client\Domain\ValueObject\ClinicId;
use App\Context\Client\Infrastructure\Persistence\Doctrine\Entity\ClientNoteEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineClientNoteReadRepository implements ClientNoteReadRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    public function listForClient(ClinicId $clinicId, ClientId $clientId): array
    {
        $entities = $this->em->getRepository(ClientNoteEntity::class)->findBy(
            [
                'clinicId' => Uuid::fromString($clinicId->toString()),
                'clientId' => Uuid::fromString($clientId->toString()),
            ],
            ['createdAt' => 'DESC', 'id' => 'DESC'],
        );

        return array_map(
            static fn (ClientNoteEntity $entity): ClientNoteView => new ClientNoteView(
                id: $entity->id->toString(),
                kind: $entity->kind->value,
                content: $entity->content,
                authorName: $entity->authorName,
                createdAt: $entity->createdAt,
            ),
            $entities,
        );
    }
}
