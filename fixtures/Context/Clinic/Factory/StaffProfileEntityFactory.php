<?php

declare(strict_types=1);

namespace App\Fixtures\Context\Clinic\Factory;

use App\Context\Clinic\Domain\Staff\ValueObject\ProfessionalTitle;
use App\Context\Clinic\Infrastructure\Persistence\Doctrine\Entity\StaffProfileEntity;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<StaffProfileEntity>
 */
final class StaffProfileEntityFactory extends PersistentProxyObjectFactory
{
    private const array AGENDA_COLORS = [
        '#4f86c6', '#e27a6d', '#6dbe78', '#c67db5', '#d4a843',
        '#5fc0c9', '#e28d4a', '#7986cb', '#81c784', '#ef9a9a',
    ];

    public static function class(): string
    {
        return StaffProfileEntity::class;
    }

    public function forMembership(StaffProfileEntity|Uuid $membershipId): self
    {
        $id = $membershipId instanceof Uuid ? $membershipId : $membershipId->getMembershipId();

        return $this->with(['membershipId' => $id]);
    }

    public function withVeterinaryCredentials(
        string $registrationNumber,
        ProfessionalTitle $professionalTitle = ProfessionalTitle::DR,
        ?string $signatureImageKey = null,
    ): self {
        return $this->with([
            'registrationNumber' => $registrationNumber,
            'professionalTitle'  => $professionalTitle,
            'signatureImageKey'  => $signatureImageKey,
        ]);
    }

    public function asDoctor(string $registrationNumber = 'FR-00001'): self
    {
        return $this->with([
            'registrationNumber' => $registrationNumber,
            'professionalTitle'  => ProfessionalTitle::DR,
        ]);
    }

    public function asProfessor(string $registrationNumber = 'FR-00002'): self
    {
        return $this->with([
            'registrationNumber' => $registrationNumber,
            'professionalTitle'  => ProfessionalTitle::PR,
        ]);
    }

    public function withoutCredentials(): self
    {
        return $this->with([
            'registrationNumber' => null,
            'professionalTitle'  => null,
            'signatureImageKey'  => null,
        ]);
    }

    public function hiddenFromAgenda(): self
    {
        return $this->with(['isVisibleInAgenda' => false]);
    }

    protected function defaults(): array
    {
        $firstName   = self::faker()->firstName();
        $lastName    = self::faker()->lastName();
        $displayName = $firstName . ' ' . $lastName;

        return [
            'id'                 => Uuid::v7(),
            'membershipId'       => Uuid::v7(),
            'firstName'          => $firstName,
            'lastName'           => $lastName,
            'displayName'        => mb_substr($displayName, 0, 60),
            'phoneNumber'        => null,
            'agendaColor'        => self::faker()->randomElement(self::AGENDA_COLORS),
            'sortOrder'          => self::faker()->numberBetween(0, 20),
            'isVisibleInAgenda'  => true,
            'registrationNumber' => null,
            'professionalTitle'  => null,
            'signatureImageKey'  => null,
            'createdAt'          => \DateTimeImmutable::createFromMutable(self::faker()->dateTimeBetween('-1 year')),
            'updatedAt'          => \DateTimeImmutable::createFromMutable(self::faker()->dateTimeBetween('-6 months')),
        ];
    }
}
