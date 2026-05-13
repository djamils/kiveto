<?php

declare(strict_types=1);

namespace App\System\Money\Infrastructure\Persistence\Doctrine\Repository;

use App\System\Money\Domain\ExchangeRate;
use App\System\Money\Domain\Repository\ExchangeRateRepository;
use App\System\Money\Domain\ValueObject\ExchangeRatePair;
use App\System\Money\Infrastructure\Persistence\Doctrine\Entity\ExchangeRateEntity;
use App\System\Money\Infrastructure\Persistence\Doctrine\Mapper\ExchangeRateMapper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineExchangeRateRepository implements ExchangeRateRepository
{
    public function __construct(
        private EntityManagerInterface $em,
        private ExchangeRateMapper $mapper,
    ) {
    }

    public function save(ExchangeRate $exchangeRate): void
    {
        $entity   = $this->mapper->toEntity($exchangeRate);
        $existing = $this->em->find(ExchangeRateEntity::class, Uuid::fromString($exchangeRate->id()->toString()));

        if (null === $existing) {
            $this->em->persist($entity);
        } else {
            $existing->setRate($entity->getRate());
            $existing->setEffectiveDate($entity->getEffectiveDate());
            $existing->setSource($entity->getSource());
        }

        $this->em->flush();
    }

    public function findRateAt(ExchangeRatePair $pair, \DateTimeImmutable $date): ?ExchangeRate
    {
        $entity = $this->em->getRepository(ExchangeRateEntity::class)->createQueryBuilder('er')
            ->where('er.currencyFrom = :from')
            ->andWhere('er.currencyTo = :to')
            ->andWhere('er.effectiveDate <= :date')
            ->setParameter('from', $pair->from()->toString())
            ->setParameter('to', $pair->to()->toString())
            ->setParameter('date', $date)
            ->orderBy('er.effectiveDate', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        if (null === $entity) {
            return null;
        }

        \assert($entity instanceof ExchangeRateEntity);

        return $this->mapper->toDomain($entity);
    }
}
