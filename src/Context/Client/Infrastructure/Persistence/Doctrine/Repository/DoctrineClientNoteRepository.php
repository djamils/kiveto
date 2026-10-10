<?php

declare(strict_types=1);

namespace App\Context\Client\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Client\Domain\ClientNote;
use App\Context\Client\Domain\Repository\ClientNoteRepositoryInterface;
use App\Context\Client\Infrastructure\Persistence\Doctrine\Mapper\ClientNoteMapper;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineClientNoteRepository implements ClientNoteRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private ClientNoteMapper $mapper,
    ) {
    }

    public function save(ClientNote $note): void
    {
        // Entries are append-only: there is nothing to update on an existing row.
        $this->em->persist($this->mapper->toEntity($note));
        $this->em->flush();
    }
}
