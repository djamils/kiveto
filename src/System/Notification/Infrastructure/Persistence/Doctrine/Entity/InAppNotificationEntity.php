<?php

declare(strict_types=1);

namespace App\System\Notification\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Rows are written and read in SQL by DbalInAppNotificationRepository
 * (INSERT IGNORE on the key and user).
 */
#[ORM\Entity]
#[ORM\Table]
#[ORM\UniqueConstraint(name: 'uniq_notif_inapp_key_user', columns: ['idempotency_key', 'user_id'])]
#[ORM\Index(name: 'idx_notif_inapp_user_clinic', columns: ['user_id', 'clinic_id', 'created_at'])]
class InAppNotificationEntity
{
    /**
     * @param array<string, string> $routeParams
     */
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: UuidType::NAME)]
        private Uuid $id,
        #[ORM\Column(name: 'clinic_id', type: UuidType::NAME)]
        private Uuid $clinicId,
        #[ORM\Column(name: 'user_id', type: UuidType::NAME)]
        private Uuid $userId,
        #[ORM\Column(type: 'string', length: 24)]
        private string $kind,
        #[ORM\Column(type: 'string', length: 255)]
        private string $title,
        #[ORM\Column(type: 'string', length: 500)]
        private string $detail,
        #[ORM\Column(type: 'string', length: 120)]
        private string $route,
        #[ORM\Column(name: 'route_params', type: 'json')]
        private array $routeParams,
        #[ORM\Column(name: 'actor_initials', type: 'string', length: 4, nullable: true)]
        private ?string $actorInitials,
        #[ORM\Column(name: 'idempotency_key', type: 'string', length: 190)]
        private string $idempotencyKey,
        #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
        private \DateTimeImmutable $createdAt,
        #[ORM\Column(name: 'read_at', type: 'datetime_immutable', nullable: true)]
        private ?\DateTimeImmutable $readAt,
    ) {
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getClinicId(): Uuid
    {
        return $this->clinicId;
    }

    public function getUserId(): Uuid
    {
        return $this->userId;
    }

    public function getKind(): string
    {
        return $this->kind;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDetail(): string
    {
        return $this->detail;
    }

    public function getRoute(): string
    {
        return $this->route;
    }

    /**
     * @return array<string, string>
     */
    public function getRouteParams(): array
    {
        return $this->routeParams;
    }

    public function getActorInitials(): ?string
    {
        return $this->actorInitials;
    }

    public function getIdempotencyKey(): string
    {
        return $this->idempotencyKey;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getReadAt(): ?\DateTimeImmutable
    {
        return $this->readAt;
    }
}
