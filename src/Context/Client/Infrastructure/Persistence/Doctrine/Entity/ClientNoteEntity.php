<?php

declare(strict_types=1);

namespace App\Context\Client\Infrastructure\Persistence\Doctrine\Entity;

use App\Context\Client\Domain\ValueObject\ClientNoteKind;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'client__client_notes')]
#[ORM\Index(name: 'idx_client_note_client_created', columns: ['clinic_id', 'client_id', 'created_at'])]
class ClientNoteEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: UuidType::NAME)]
        public Uuid $id,
        #[ORM\Column(type: UuidType::NAME)]
        public Uuid $clinicId,
        #[ORM\Column(type: UuidType::NAME)]
        public Uuid $clientId,
        #[ORM\Column(type: 'string', length: 20, enumType: ClientNoteKind::class)]
        public ClientNoteKind $kind,
        #[ORM\Column(type: 'text')]
        public string $content,
        #[ORM\Column(type: 'string', length: 255)]
        public string $authorName,
        #[ORM\Column(type: 'datetime_immutable')]
        public \DateTimeImmutable $createdAt,
    ) {
    }
}
