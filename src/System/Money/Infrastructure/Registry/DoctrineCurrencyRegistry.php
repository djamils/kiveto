<?php

declare(strict_types=1);

namespace App\System\Money\Infrastructure\Registry;

use App\Shared\Domain\ValueObject\CurrencyCode;
use App\System\Money\Domain\Currency;
use App\System\Money\Domain\Exception\UnknownCurrencyException;
use App\System\Money\Domain\Service\CurrencyRegistry;
use App\System\Money\Infrastructure\Persistence\Doctrine\Entity\CurrencyEntity;
use App\System\Money\Infrastructure\Persistence\Doctrine\Mapper\CurrencyMapper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final readonly class DoctrineCurrencyRegistry implements CurrencyRegistry
{
    public function __construct(
        private EntityManagerInterface $em,
        private CurrencyMapper $mapper,
        #[Autowire(service: 'cache.app')]
        private CacheInterface $cache,
    ) {
    }

    public function get(CurrencyCode $code): Currency
    {
        $currency = $this->cache->get('money_currency_' . $code->toString(), function (ItemInterface $item) use ($code): ?Currency {
            $item->expiresAfter(3600);

            $entity = $this->em->find(CurrencyEntity::class, $code->toString());

            if (null === $entity) {
                return null;
            }

            return $this->mapper->toDomain($entity);
        });

        if (null === $currency) {
            throw new UnknownCurrencyException($code);
        }

        return $currency;
    }

    /** @return list<Currency> */
    public function listActive(): array
    {
        /** @var list<CurrencyEntity> $entities */
        $entities = $this->em->getRepository(CurrencyEntity::class)->findBy(['active' => true]);

        return array_map(
            fn (CurrencyEntity $entity) => $this->mapper->toDomain($entity),
            $entities,
        );
    }

    public function has(CurrencyCode $code): bool
    {
        $count = $this->em->getRepository(CurrencyEntity::class)->count(['code' => $code->toString()]);

        return $count > 0;
    }
}
