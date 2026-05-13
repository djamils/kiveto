<?php

declare(strict_types=1);

namespace App\System\Money\Infrastructure\Persistence\Doctrine\Repository;

use App\Shared\Domain\ValueObject\CurrencyCode;
use App\System\Money\Domain\Currency;
use App\System\Money\Domain\Repository\CurrencyRepository;
use App\System\Money\Infrastructure\Persistence\Doctrine\Entity\CurrencyEntity;
use App\System\Money\Infrastructure\Persistence\Doctrine\Mapper\CurrencyMapper;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineCurrencyRepository implements CurrencyRepository
{
    public function __construct(
        private EntityManagerInterface $em,
        private CurrencyMapper $mapper,
    ) {
    }

    public function save(Currency $currency): void
    {
        $entity   = $this->mapper->toEntity($currency);
        $existing = $this->em->find(CurrencyEntity::class, $entity->getCode());

        if (null === $existing) {
            $this->em->persist($entity);
        } else {
            $existing->setSymbol($entity->getSymbol());
            $existing->setDecimals($entity->getDecimals());
            $existing->setDisplayName($entity->getDisplayName());
            $existing->setActive($entity->isActive());
            $existing->setUpdatedAt($entity->getUpdatedAt());
        }

        $this->em->flush();
    }

    public function findByCode(CurrencyCode $code): ?Currency
    {
        $entity = $this->em->find(CurrencyEntity::class, $code->toString());

        if (null === $entity) {
            return null;
        }

        return $this->mapper->toDomain($entity);
    }
}
