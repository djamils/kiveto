<?php

declare(strict_types=1);

namespace App\System\Taxation\Infrastructure\Persistence\Doctrine\Repository;

use App\System\Taxation\Domain\Entity\MentionTemplate;
use App\System\Taxation\Domain\Repository\MentionTemplateRepositoryInterface;
use App\System\Taxation\Domain\ValueObject\MentionTemplateId;
use App\System\Taxation\Domain\ValueObject\TaxRegimeId;
use App\System\Taxation\Infrastructure\Persistence\Doctrine\Entity\MentionTemplateEntity;
use App\System\Taxation\Infrastructure\Persistence\Doctrine\Mapper\MentionTemplateMapper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineMentionTemplateRepository implements MentionTemplateRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private MentionTemplateMapper $mapper,
    ) {
    }

    public function save(MentionTemplate $template): void
    {
        $entity   = $this->mapper->toEntity($template);
        $existing = $this->em->find(MentionTemplateEntity::class, Uuid::fromRfc4122($template->id()->toString()));

        if (null === $existing) {
            $this->em->persist($entity);
        } else {
            $existing->setRegimeId($entity->getRegimeId());
            $existing->setCode($entity->getCode());
            $existing->setConditionJson($entity->getConditionJson());
            $existing->setTemplate($entity->getTemplate());
            $existing->setLocale($entity->getLocale());
            $existing->setActive($entity->isActive());
        }

        $this->em->flush();
    }

    public function findById(MentionTemplateId $id): ?MentionTemplate
    {
        $entity = $this->em->find(MentionTemplateEntity::class, Uuid::fromRfc4122($id->toString()));

        if (null === $entity) {
            return null;
        }

        return $this->mapper->toDomain($entity);
    }

    /** @return list<MentionTemplate> */
    public function findActiveByRegime(TaxRegimeId $regimeId): array
    {
        /** @var list<MentionTemplateEntity> $entities */
        $entities = $this->em->getRepository(MentionTemplateEntity::class)->findBy([
            'regimeId' => $regimeId->toString(),
            'active'   => true,
        ]);

        return array_map(fn (MentionTemplateEntity $e) => $this->mapper->toDomain($e), $entities);
    }

    public function deleteByRegime(TaxRegimeId $regimeId): void
    {
        $this->em->createQueryBuilder()
            ->delete(MentionTemplateEntity::class, 'm')
            ->where('m.regimeId = :regimeId')
            ->setParameter('regimeId', $regimeId->toString())
            ->getQuery()
            ->execute()
        ;
    }
}
