<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\CountUnreadDocumentsPerClinic;

use App\Context\ClientPortal\Application\Port\PublishedDocumentReadModelInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CountUnreadDocumentsPerClinicHandler
{
    public function __construct(private PublishedDocumentReadModelInterface $documents)
    {
    }

    /**
     * @return array<string, int>
     */
    public function __invoke(CountUnreadDocumentsPerClinic $query): array
    {
        return $this->documents->unreadPerClinic($query->userId);
    }
}
