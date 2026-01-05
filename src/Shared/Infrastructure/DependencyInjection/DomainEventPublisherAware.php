<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\DependencyInjection;

use App\Shared\Application\Event\DomainEventPublisher;
use Symfony\Contracts\Service\Attribute\Required;

trait DomainEventPublisherAware
{
    protected DomainEventPublisher $domainEventPublisher;

    #[Required]
    public function setDomainEventPublisher(DomainEventPublisher $domainEventPublisher): void
    {
        $this->domainEventPublisher = $domainEventPublisher;
    }
}
