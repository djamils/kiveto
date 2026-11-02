<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\CountUnreadDocumentsPerClinic;

use App\Context\ClientPortal\Application\Port\PublishedDocumentReadModelInterface;
use App\Context\ClientPortal\Application\Service\ListableSources;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Counts what the list shows: the same live source check (one batch per
 * clinic), so that the badge never counts a document the list hides. A
 * clinic without any unread document is left out.
 */
#[AsMessageHandler]
final readonly class CountUnreadDocumentsPerClinicHandler
{
    public function __construct(
        private PublishedDocumentReadModelInterface $documents,
        private ListableSources $sources,
    ) {
    }

    /**
     * @return array<string, int>
     */
    public function __invoke(CountUnreadDocumentsPerClinic $query): array
    {
        $counts = [];

        foreach ($this->documents->unreadPerClinic($query->userId) as $clinicId => $rows) {
            $unread = \count($this->sources->keep($clinicId, $rows));

            if ($unread > 0) {
                $counts[$clinicId] = $unread;
            }
        }

        return $counts;
    }
}
