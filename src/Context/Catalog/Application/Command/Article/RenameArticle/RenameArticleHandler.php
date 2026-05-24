<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Command\Article\RenameArticle;

use App\Context\Catalog\Domain\Article\Exception\ArticleNotFoundException;
use App\Context\Catalog\Domain\Article\Repository\ArticleRepositoryInterface;
use App\Context\Catalog\Domain\Article\ValueObject\ArticleId;
use App\Context\Catalog\Domain\Article\ValueObject\ArticleName;
use App\Context\Catalog\Domain\ValueObject\ClinicId;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class RenameArticleHandler
{
    public function __construct(
        private readonly ArticleRepositoryInterface $articleRepository,
        private readonly ClockInterface $clock,
        private readonly DomainEventPublisher $domainEventPublisher,
    ) {
    }

    public function __invoke(RenameArticle $command): void
    {
        $clinicId = ClinicId::fromString($command->clinicId);
        $article  = $this->articleRepository->findById(ArticleId::fromString($command->articleId), $clinicId);

        if (null === $article) {
            throw new ArticleNotFoundException($command->articleId);
        }

        $article->rename(ArticleName::fromString($command->name), $this->clock->now());

        $this->articleRepository->save($article);
        $this->domainEventPublisher->publish($article);
    }
}
