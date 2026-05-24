<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Query\Pricing\ListPriceLists;

use App\Context\Catalog\Infrastructure\Persistence\Doctrine\Entity\PriceListEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final readonly class ListPriceListsHandler
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    public function __invoke(ListPriceLists $query): PriceListCollection
    {
        $tenantId = Uuid::fromString($query->clinicId);

        /** @var list<PriceListEntity> $entities */
        $entities = $this->em->getRepository(PriceListEntity::class)->findBy([
            'tenantId' => $tenantId,
        ]);

        $items = array_map(
            static fn (PriceListEntity $e) => new PriceListSummary(
                id: $e->getId()->toString(),
                name: $e->getName(),
                isDefault: $e->getIsDefault(),
                status: $e->getStatus()->value,
                updatedAt: $e->getUpdatedAt()->format(\DateTimeInterface::ATOM),
            ),
            $entities,
        );

        return new PriceListCollection(items: $items);
    }
}
