<?php

declare(strict_types=1);

namespace App\Context\Catalog\Domain\Article\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

final readonly class ArticleArchived extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'catalog';
    protected const int    VERSION         = 1;

    public function __construct(
        public string $articleId,
        public string $clinicId,
    ) {
    }

    public function aggregateId(): string
    {
        return $this->articleId;
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return [
            'articleId' => $this->articleId,
            'clinicId'  => $this->clinicId,
        ];
    }
}
