<?php

declare(strict_types=1);

namespace App\Context\Client\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Client\Domain\ClientCommunication;
use App\Context\Client\Domain\Repository\ClientCommunicationRepositoryInterface;
use App\Context\Client\Infrastructure\Persistence\Doctrine\Mapper\ClientCommunicationMapper;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineClientCommunicationRepository implements ClientCommunicationRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private ClientCommunicationMapper $mapper,
    ) {
    }

    public function save(ClientCommunication $communication): void
    {
        // Entries are append-only: there is nothing to update on an existing row.
        $this->em->persist($this->mapper->toEntity($communication));
        $this->em->flush();
    }
}
