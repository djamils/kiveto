<?php

declare(strict_types=1);

namespace App\System\Taxation\Application\Command\LoadTaxRegime;

use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use App\System\Taxation\Application\Port\TaxRegimeLoaderInterface;
use App\System\Taxation\Domain\Repository\MentionTemplateRepositoryInterface;
use App\System\Taxation\Domain\Repository\TaxRegimeRepositoryInterface;
use App\System\Taxation\Domain\TaxRegime;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class LoadTaxRegimeHandler
{
    public function __construct(
        private TaxRegimeRepositoryInterface $regimeRepo,
        private MentionTemplateRepositoryInterface $mentionRepo,
        private TaxRegimeLoaderInterface $loader,
        private DomainEventPublisher $domainEventPublisher,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(LoadTaxRegime $command): void
    {
        $blueprint = $this->loader->load($command->regimeId);
        $existing  = $this->regimeRepo->findById($command->regimeId);

        if (null === $existing) {
            $regime = TaxRegime::create(
                $blueprint->regimeId,
                $blueprint->name,
                $blueprint->country,
                $this->clock,
            );
        } else {
            $regime = $existing;
        }

        $regime->replaceAllRates($blueprint->rates, $this->clock);

        if ($blueprint->active && !$regime->isActive()) {
            $regime->activate($this->clock);
        }

        $this->regimeRepo->save($regime);
        $this->mentionRepo->deleteByRegime($command->regimeId);

        foreach ($blueprint->mentionTemplates as $template) {
            $this->mentionRepo->save($template);
        }

        $this->domainEventPublisher->publish($regime);
    }
}
