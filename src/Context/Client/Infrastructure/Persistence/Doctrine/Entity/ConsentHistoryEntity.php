<?php

declare(strict_types=1);

namespace App\Context\Client\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Schema of the consent history (append-only); rows are written in SQL by
 * DbalConsentHistoryRepository and read by DbalClientConsentReader.
 */
#[ORM\Entity]
#[ORM\Table(name: 'client__consent_history')]
#[ORM\Index(name: 'idx_client_consent_history_client', columns: ['clinic_id', 'client_id', 'at', 'id'])]
class ConsentHistoryEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    public Uuid $id;

    #[ORM\Column(type: UuidType::NAME)]
    public Uuid $clinicId;

    #[ORM\Column(type: UuidType::NAME)]
    public Uuid $clientId;

    #[ORM\Column(type: 'string', length: 30)]
    public string $kind;

    #[ORM\Column(type: 'string', length: 10)]
    public string $channel;

    #[ORM\Column(type: 'string', length: 10)]
    public string $action;

    #[ORM\Column(type: 'datetime_immutable')]
    public \DateTimeImmutable $at;

    #[ORM\Column(type: 'string', length: 10)]
    public string $source;

    #[ORM\Column(type: 'string', length: 20)]
    public string $actorType;

    #[ORM\Column(type: UuidType::NAME, nullable: true)]
    public ?Uuid $actorId;

    #[ORM\Column(type: 'string', length: 255)]
    public string $authorLabel;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    public ?string $wordingVersion;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    public bool $approximate = false;
}
