<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\EventHandler;

use App\Context\Catalog\Application\Port\ArticleReadRepositoryInterface;
use App\Context\Catalog\Domain\Article\Repository\ArticleRepositoryInterface;
use App\Context\Catalog\Domain\Article\ValueObject\MarketingAuthorizationRef;
use App\Context\Catalog\Domain\Article\ValueObject\PrescriptionClass;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use App\System\PharmaceuticalRegistry\Domain\Event\ControlledSubstanceClassificationChanged;
use App\System\PharmaceuticalRegistry\Domain\Event\MarketingAuthorizationWithdrawn;
use App\System\PharmaceuticalRegistry\Domain\Event\PrescriptionRequirementChanged;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'messenger.bus.integration_event', method: 'onPrescriptionRequirementChanged')]
#[AsMessageHandler(bus: 'messenger.bus.integration_event', method: 'onControlledSubstanceClassificationChanged')]
#[AsMessageHandler(bus: 'messenger.bus.integration_event', method: 'onMarketingAuthorizationWithdrawn')]
final class SyncCatalogOnPharmaceuticalChange
{
    public function __construct(
        private readonly ArticleReadRepositoryInterface $articleReadRepository,
        private readonly ArticleRepositoryInterface $articleRepository,
        private readonly DomainEventPublisher $domainEventPublisher,
        private readonly ClockInterface $clock,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function onPrescriptionRequirementChanged(PrescriptionRequirementChanged $event): void
    {
        $ref      = MarketingAuthorizationRef::fromString($event->marketingAuthorizationId);
        $articles = $this->articleReadRepository->findAllByAuthorizationRef($ref);
        $now      = $this->clock->now();

        foreach ($articles as $article) {
            try {
                $prescriptionClass = PrescriptionClass::from($event->newClass);
            } catch (\ValueError) {
                $this->logger->warning(\sprintf(
                    'Unknown prescription class "%s" for article "%s" — skipping.',
                    $event->newClass,
                    $article->id()->toString(),
                ));
                continue;
            }

            $article->updatePrescriptionFlags(
                requiresPrescription: true,
                prescriptionClass: $prescriptionClass,
                isControlledSubstance: $article->drugProperties()?->isControlledSubstance() ?? false,
                updatedAt: $now,
            );

            $this->articleRepository->save($article);
            $this->domainEventPublisher->publish($article);
        }
    }

    public function onControlledSubstanceClassificationChanged(ControlledSubstanceClassificationChanged $event): void
    {
        $ref      = MarketingAuthorizationRef::fromString($event->marketingAuthorizationId);
        $articles = $this->articleReadRepository->findAllByAuthorizationRef($ref);
        $now      = $this->clock->now();

        foreach ($articles as $article) {
            $drugProps = $article->drugProperties();

            $article->updatePrescriptionFlags(
                requiresPrescription: $drugProps?->requiresPrescription() ?? false,
                prescriptionClass: $drugProps?->prescriptionClass(),
                isControlledSubstance: null !== $event->newClass,
                updatedAt: $now,
            );

            $this->articleRepository->save($article);
            $this->domainEventPublisher->publish($article);
        }
    }

    public function onMarketingAuthorizationWithdrawn(MarketingAuthorizationWithdrawn $event): void
    {
        $ref      = MarketingAuthorizationRef::fromString($event->marketingAuthorizationId);
        $articles = $this->articleReadRepository->findAllByAuthorizationRef($ref);
        $now      = $this->clock->now();

        foreach ($articles as $article) {
            $article->archive($now);
            $this->articleRepository->save($article);
            $this->domainEventPublisher->publish($article);
        }
    }
}
