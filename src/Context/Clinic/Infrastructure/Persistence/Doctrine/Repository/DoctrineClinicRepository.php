<?php

declare(strict_types=1);

namespace App\Context\Clinic\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Clinic\Domain\Clinic;
use App\Context\Clinic\Domain\Repository\ClinicRepositoryInterface;
use App\Context\Clinic\Domain\ValueObject\ClinicId;
use App\Context\Clinic\Domain\ValueObject\ClinicSlug;
use App\Context\Clinic\Infrastructure\Persistence\Doctrine\Entity\ClinicEntity;
use App\Context\Clinic\Infrastructure\Persistence\Doctrine\Mapper\ClinicMapper;
use App\Shared\Domain\ValueObject\Siren;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineClinicRepository implements ClinicRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private ClinicMapper $mapper,
    ) {
    }

    public function save(Clinic $clinic): void
    {
        $entity = $this->mapper->toEntity($clinic);

        $existing = $this->em->find(ClinicEntity::class, $entity->getId());

        if (null === $existing) {
            $this->em->persist($entity);
        } else {
            $existing->setName($entity->getName());
            $existing->setSlug($entity->getSlug());
            $existing->setTimeZone($entity->getTimeZone());
            $existing->setLocale($entity->getLocale());
            $existing->setStatus($entity->getStatus());
            $existing->setClinicGroupId($entity->getClinicGroupId());
            // What the clinic prints, when it opens and how it bills are part of
            // the aggregate too: leaving them out of the copy silently dropped
            // every change made to them.
            $existing->setLetterhead($entity->getLetterhead());
            $existing->setOpeningHours($entity->getOpeningHours());
            $existing->setBillingSettings($entity->getBillingSettings());
            $existing->setBrandColor($entity->getBrandColor());
            $existing->setLogoKey($entity->getLogoKey());
            $existing->setUpdatedAt($entity->getUpdatedAt());
        }

        $this->em->flush();
    }

    public function findById(ClinicId $id): ?Clinic
    {
        $entity = $this->em->getRepository(ClinicEntity::class)->find(
            Uuid::fromString($id->toString()),
        );

        if (null === $entity) {
            return null;
        }

        return $this->mapper->toDomain($entity);
    }

    public function findBySlug(ClinicSlug $slug): ?Clinic
    {
        $entity = $this->em->getRepository(ClinicEntity::class)->findOneBy([
            'slug' => $slug->toString(),
        ]);

        if (null === $entity) {
            return null;
        }

        return $this->mapper->toDomain($entity);
    }

    public function existsBySlug(ClinicSlug $slug): bool
    {
        $count = $this->em->getRepository(ClinicEntity::class)->count([
            'slug' => $slug->toString(),
        ]);

        return $count > 0;
    }

    public function findOthersWithSiren(Siren $siren, ClinicId $excluding): array
    {
        /** @var list<ClinicEntity> $entities */
        $entities = $this->em->createQueryBuilder()
            ->select('c')
            ->from(ClinicEntity::class, 'c')
            ->where('c.letterhead.siret LIKE :siren')
            ->andWhere('c.id != :excluding')
            ->setParameter('siren', $siren->value() . '%')
            ->setParameter('excluding', Uuid::fromString($excluding->toString()), UuidType::NAME)
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        $clinics = array_map($this->mapper->toDomain(...), $entities);

        // A stored SIRET that merely starts with these digits but is not a valid
        // one does not make its clinic an establishment of the company.
        return array_values(array_filter(
            $clinics,
            static fn (Clinic $clinic): bool => $clinic->letterhead()->siren()?->equals($siren) ?? false,
        ));
    }
}
