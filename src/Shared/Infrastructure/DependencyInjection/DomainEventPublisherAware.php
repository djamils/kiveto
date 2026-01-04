<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\DependencyInjection;

use App\Shared\Application\Event\DomainEventPublisher;
use Symfony\Contracts\Service\Attribute\Required;

trait DomainEventPublisherAware
{
    protected DomainEventPublisher $eventPublisher;

    #[Required]
    public function setDomainEventPublisher(DomainEventPublisher $eventPublisher): void
    {
        $this->eventPublisher = $eventPublisher;
    }
}
