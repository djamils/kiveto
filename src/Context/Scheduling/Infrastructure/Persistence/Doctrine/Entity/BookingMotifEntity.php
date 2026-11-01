<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'scheduling__booking_motifs')]
#[ORM\Index(name: 'idx_sched_motif_clinic', columns: ['clinic_id', 'sort_order'])]
class BookingMotifEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(name: 'clinic_id', type: UuidType::NAME)]
    private Uuid $clinicId;

    #[ORM\Column(type: 'string', length: 80)]
    private string $label;

    #[ORM\Column(type: 'string', length: 500, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'duration_min', type: 'integer')]
    private int $durationMin;

    #[ORM\Column(name: 'online_enabled', type: 'boolean')]
    private bool $onlineEnabled;

    #[ORM\Column(name: 'confirmation_known_client', type: 'string', length: 16)]
    private string $confirmationKnownClient;

    /**
     * @var list<string>
     */
    #[ORM\Column(name: 'allowed_block_types', type: 'json')]
    private array $allowedBlockTypes = [];

    #[ORM\Column(name: 'preventive_care_kind', type: 'string', length: 32, nullable: true)]
    private ?string $preventiveCareKind = null;

    #[ORM\Column(name: 'new_clients_allowed', type: 'boolean')]
    private bool $newClientsAllowed;

    #[ORM\Column(name: 'sort_order', type: 'integer')]
    private int $sortOrder;

    #[ORM\Column(name: 'archived_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $archivedAt = null;

    /**
     * @var Collection<int, BookingMotifPractitionerEntity>
     */
    #[ORM\OneToMany(targetEntity: BookingMotifPractitionerEntity::class, mappedBy: 'motif', cascade: ['persist'], orphanRemoval: true)]
    private Collection $practitioners;

    public function __construct()
    {
        $this->practitioners = new ArrayCollection();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function setId(Uuid $id): void
    {
        $this->id = $id;
    }

    public function getClinicId(): Uuid
    {
        return $this->clinicId;
    }

    public function setClinicId(Uuid $clinicId): void
    {
        $this->clinicId = $clinicId;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): void
    {
        $this->label = $label;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getDurationMin(): int
    {
        return $this->durationMin;
    }

    public function setDurationMin(int $durationMin): void
    {
        $this->durationMin = $durationMin;
    }

    public function isOnlineEnabled(): bool
    {
        return $this->onlineEnabled;
    }

    public function setOnlineEnabled(bool $onlineEnabled): void
    {
        $this->onlineEnabled = $onlineEnabled;
    }

    public function getConfirmationKnownClient(): string
    {
        return $this->confirmationKnownClient;
    }

    public function setConfirmationKnownClient(string $confirmationKnownClient): void
    {
        $this->confirmationKnownClient = $confirmationKnownClient;
    }

    /**
     * @return list<string>
     */
    public function getAllowedBlockTypes(): array
    {
        return $this->allowedBlockTypes;
    }

    /**
     * @param list<string> $allowedBlockTypes
     */
    public function setAllowedBlockTypes(array $allowedBlockTypes): void
    {
        $this->allowedBlockTypes = $allowedBlockTypes;
    }

    public function getPreventiveCareKind(): ?string
    {
        return $this->preventiveCareKind;
    }

    public function setPreventiveCareKind(?string $preventiveCareKind): void
    {
        $this->preventiveCareKind = $preventiveCareKind;
    }

    public function isNewClientsAllowed(): bool
    {
        return $this->newClientsAllowed;
    }

    public function setNewClientsAllowed(bool $newClientsAllowed): void
    {
        $this->newClientsAllowed = $newClientsAllowed;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): void
    {
        $this->sortOrder = $sortOrder;
    }

    public function getArchivedAt(): ?\DateTimeImmutable
    {
        return $this->archivedAt;
    }

    public function setArchivedAt(?\DateTimeImmutable $archivedAt): void
    {
        $this->archivedAt = $archivedAt;
    }

    /**
     * @return Collection<int, BookingMotifPractitionerEntity>
     */
    public function getPractitioners(): Collection
    {
        return $this->practitioners;
    }

    /**
     * Keeps the rows of the practitioners still listed (in that order of
     * insertion), adds the new ones and removes the others, so a kept
     * practitioner is never deleted and re-inserted in the same flush.
     *
     * @param list<Uuid> $userIds
     */
    public function syncPractitioners(array $userIds): void
    {
        $wanted = [];

        foreach ($userIds as $userId) {
            $wanted[$userId->toRfc4122()] = $userId;
        }

        foreach ($this->practitioners as $row) {
            $key = $row->getUserId()->toRfc4122();

            if (isset($wanted[$key])) {
                unset($wanted[$key]);
            } else {
                $this->practitioners->removeElement($row);
            }
        }

        foreach ($wanted as $userId) {
            $this->practitioners->add(new BookingMotifPractitionerEntity($this, $userId));
        }
    }
}
