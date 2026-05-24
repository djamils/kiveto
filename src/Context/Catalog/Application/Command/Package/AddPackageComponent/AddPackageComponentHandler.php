<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Command\Package\AddPackageComponent;

use App\Context\Catalog\Domain\Act\Repository\ActRepositoryInterface;
use App\Context\Catalog\Domain\Act\ValueObject\ActId;
use App\Context\Catalog\Domain\Act\ValueObject\ActStatus;
use App\Context\Catalog\Domain\Article\Repository\ArticleRepositoryInterface;
use App\Context\Catalog\Domain\Article\ValueObject\ArticleId;
use App\Context\Catalog\Domain\Article\ValueObject\ArticleStatus;
use App\Context\Catalog\Domain\Package\Exception\ArchivedItemInPackageException;
use App\Context\Catalog\Domain\Package\Exception\PackageNotFoundException;
use App\Context\Catalog\Domain\Package\Repository\PackageRepositoryInterface;
use App\Context\Catalog\Domain\Package\ValueObject\PackageId;
use App\Context\Catalog\Domain\Package\ValueObject\Quantity;
use App\Context\Catalog\Domain\Shared\ValueObject\CatalogItemRef;
use App\Context\Catalog\Domain\Shared\ValueObject\CatalogItemType;
use App\Context\Catalog\Domain\ValueObject\ClinicId;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class AddPackageComponentHandler
{
    public function __construct(
        private readonly PackageRepositoryInterface $packageRepository,
        private readonly ActRepositoryInterface $actRepository,
        private readonly ArticleRepositoryInterface $articleRepository,
        private readonly ClockInterface $clock,
        private readonly DomainEventPublisher $domainEventPublisher,
    ) {
    }

    public function __invoke(AddPackageComponent $command): void
    {
        $clinicId = ClinicId::fromString($command->clinicId);
        $package  = $this->packageRepository->findById(PackageId::fromString($command->packageId), $clinicId);

        if (null === $package) {
            throw new PackageNotFoundException($command->packageId);
        }

        $itemRef  = CatalogItemRef::fromPrimitives($command->itemType, $command->itemId);
        $archived = $this->isItemArchived($itemRef, $clinicId);

        if ($archived) {
            throw new ArchivedItemInPackageException($command->itemType, $command->itemId);
        }

        $package->addComponent(
            $itemRef,
            Quantity::of($command->quantity),
            $command->weight,
            $this->clock->now(),
        );

        $this->packageRepository->save($package);
        $this->domainEventPublisher->publish($package);
    }

    private function isItemArchived(CatalogItemRef $itemRef, ClinicId $clinicId): bool
    {
        return match ($itemRef->type()) {
            CatalogItemType::ACT => ActStatus::ARCHIVED === $this->actRepository
                ->findById(ActId::fromString($itemRef->id()), $clinicId)
                ?->status(),
            CatalogItemType::ARTICLE => ArticleStatus::ARCHIVED === $this->articleRepository
                ->findById(ArticleId::fromString($itemRef->id()), $clinicId)
                ?->status(),
        };
    }
}
