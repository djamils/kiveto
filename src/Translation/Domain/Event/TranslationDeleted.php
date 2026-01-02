<?php

declare(strict_types=1);

namespace App\Translation\Domain\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

final readonly class TranslationDeleted extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'translation';
    protected const int VERSION            = 1;

    public function __construct(
        private string $scope,
        private string $locale,
        private string $domain,
        private string $key,
        private ?string $actorId,
        private \DateTimeImmutable $removedAt,
    ) {
    }

    public function aggregateId(): string
    {
        return \sprintf('%s:%s:%s', $this->scope, $this->locale, $this->domain);
    }

    public function payload(): array
    {
        return [
            'scope'     => $this->scope,
            'locale'    => $this->locale,
            'domain'    => $this->domain,
            'key'       => $this->key,
            'actorId'   => $this->actorId,
            'removedAt' => $this->removedAt->format(\DATE_ATOM),
        ];
    }
}
