<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Support;

use App\Context\ClientPortal\Application\Port\ConsultationStartsInterface;
use App\Context\ClientPortal\Application\Port\PortalRequestBellInterface;
use App\Context\ClientPortal\Application\Port\PortalRequestReadModelInterface;
use App\Context\ClientPortal\Application\Port\PortalRequestRecord;
use App\Context\ClientPortal\Application\Port\PrescriberDirectoryInterface;
use App\Context\ClientPortal\Application\Port\RequestPosition;

/**
 * The request ports on public fields: consultations started per
 * appointment, the clinic's veterinarians and animal managers, the bells
 * rung, and the read model answering fixed records (pages recorded).
 */
final class RequestFakes implements ConsultationStartsInterface, PrescriberDirectoryInterface, PortalRequestBellInterface, PortalRequestReadModelInterface
{
    /** @var list<string> appointments a consultation was started from */
    public array $started = [];

    /** @var list<string> */
    public array $veterinarians = [];

    /** @var list<string> */
    public array $animalManagers = [];

    /** @var list<array{clinicId: string, userIds: list<string>, requestId: string, title: string, detail: string}> */
    public array $bells = [];

    /** @var list<PortalRequestRecord> answered by both pages */
    public array $records = [];

    /** @var list<string> */
    public array $pages = [];

    public function startedFrom(string $clinicId, array $appointmentIds): array
    {
        return array_values(array_intersect($appointmentIds, $this->started));
    }

    public function veterinarians(string $clinicId): array
    {
        return $this->veterinarians;
    }

    public function animalManagers(string $clinicId): array
    {
        return $this->animalManagers;
    }

    public function announce(string $clinicId, array $userIds, string $requestId, string $title, string $detail): void
    {
        $this->bells[] = ['clinicId' => $clinicId, 'userIds' => $userIds, 'requestId' => $requestId, 'title' => $title, 'detail' => $detail];
    }

    public function ownerPage(string $userId, string $clinicId, ?RequestPosition $after, int $limit): array
    {
        $this->pages[] = \sprintf('owner:%s:%s:%s:%d', $userId, $clinicId, self::position($after), $limit);

        return \array_slice($this->records, 0, $limit);
    }

    public function clinicPage(string $clinicId, array $statuses, ?string $kind, ?RequestPosition $after, int $limit): array
    {
        $this->pages[] = \sprintf('clinic:%s:%s:%s:%s:%d', $clinicId, implode(',', $statuses), $kind ?? '-', self::position($after), $limit);

        return \array_slice($this->records, 0, $limit);
    }

    private static function position(?RequestPosition $after): string
    {
        return null === $after ? '-' : $after->submittedAt->format('Y-m-d H:i:s') . '/' . $after->id;
    }
}
