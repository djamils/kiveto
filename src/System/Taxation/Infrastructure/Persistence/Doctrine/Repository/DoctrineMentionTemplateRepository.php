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
use Symfony\Contracts\Service\ResetInterface;

/**
 * A regime's active templates are read for every taxed line: they are kept
 * by regime for the request. A save or a delete forgets them, and so does
 * the kernel reset between requests and worker messages.
 */
final class DoctrineMentionTemplateRepository implements MentionTemplateRepositoryInterface, ResetInterface
{
    /** @var array<string, list<MentionTemplate>> by regime id */
    private array $activeByRegime = [];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MentionTemplateMapper $mapper,
    ) {
    }

    public function reset(): void
    {
        $this->activeByRegime = [];
    }

    public function save(MentionTemplate $template): void
    {
        $this->activeByRegime = [];
        $entity               = $this->mapper->toEntity($template);
        $existing             = $this->em->find(MentionTemplateEntity::class, Uuid::fromRfc4122($template->id()->toString()));

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
        $key = $regimeId->toString();

        if (!\array_key_exists($key, $this->activeByRegime)) {
            /** @var list<MentionTemplateEntity> $entities */
            $entities = $this->em->getRepository(MentionTemplateEntity::class)->findBy([
                'regimeId' => $key,
                'active'   => true,
            ]);

            $this->activeByRegime[$key] = array_map(fn (MentionTemplateEntity $e) => $this->mapper->toDomain($e), $entities);
        }

        return $this->activeByRegime[$key];
    }

    public function deleteByRegime(TaxRegimeId $regimeId): void
    {
        $this->activeByRegime = [];
        $this->em->createQueryBuilder()
            ->delete(MentionTemplateEntity::class, 'm')
            ->where('m.regimeId = :regimeId')
            ->setParameter('regimeId', $regimeId->toString())
            ->getQuery()
            ->execute()
        ;
    }
}
