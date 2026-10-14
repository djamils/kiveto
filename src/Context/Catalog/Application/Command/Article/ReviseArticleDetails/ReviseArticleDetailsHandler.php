<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Command\Article\ReviseArticleDetails;

use App\Context\Catalog\Domain\Article\Exception\ArticleNotFoundException;
use App\Context\Catalog\Domain\Article\Exception\DuplicateGtinException;
use App\Context\Catalog\Domain\Article\Repository\ArticleRepositoryInterface;
use App\Context\Catalog\Domain\Article\ValueObject\ArticleId;
use App\Context\Catalog\Domain\Article\ValueObject\Gtin;
use App\Context\Catalog\Domain\ValueObject\ClinicId;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\UnitOfMeasure\Service\UnitOfMeasureRegistry;
use App\Shared\Domain\UnitOfMeasure\ValueObject\UnitOfMeasure;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ReviseArticleDetailsHandler
{
    public function __construct(
        private ArticleRepositoryInterface $articleRepository,
        private UnitOfMeasureRegistry $unitOfMeasureRegistry,
        private ClockInterface $clock,
        private DomainEventPublisher $domainEventPublisher,
    ) {
    }

    public function __invoke(ReviseArticleDetails $command): void
    {
        $clinicId = ClinicId::fromString($command->clinicId);
        $article  = $this->articleRepository->findById(ArticleId::fromString($command->articleId), $clinicId);

        if (null === $article) {
            throw new ArticleNotFoundException($command->articleId);
        }

        $gtin = null !== $command->gtin ? Gtin::fromString($command->gtin) : null;

        // A barcode identifies one shelf item: another article of the clinic
        // already scanned under it keeps it.
        if (null !== $gtin) {
            $holder = $this->articleRepository->findByGtin($gtin, $clinicId);

            if (null !== $holder && !$holder->id()->equals($article->id())) {
                throw new DuplicateGtinException($gtin->toString());
            }
        }

        $unitOfMeasure = UnitOfMeasure::fromString($command->unitOfMeasure);
        $this->unitOfMeasureRegistry->resolve($unitOfMeasure);

        $article->reviseDetails($gtin, $unitOfMeasure, $command->trackStock, $this->clock->now());

        $this->articleRepository->save($article);
        $this->domainEventPublisher->publish($article);
    }
}
