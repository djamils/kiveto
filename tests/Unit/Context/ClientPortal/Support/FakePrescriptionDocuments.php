<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Support;

use App\Context\ClientPortal\Application\Port\PrescriptionContent;
use App\Context\ClientPortal\Application\Port\PrescriptionDocumentsInterface;
use App\Context\ClientPortal\Application\Port\PrescriptionPortalState;
use App\Context\ClientPortal\Application\Port\PrescriptionPublication;
use App\Context\ClientPortal\Application\Port\PublishableSource;

final class FakePrescriptionDocuments implements PrescriptionDocumentsInterface
{
    /** @var array<string, PrescriptionPublication> by prescription id */
    public array $publications = [];

    /** @var array<string, PrescriptionPortalState> by prescription id */
    public array $states = [];

    /** @var array<string, PrescriptionContent> by prescription id */
    public array $contents = [];

    /** @var list<PublishableSource> ordered by id */
    public array $publishable = [];

    /** @var list<string> `<clinic>:<prescription>:<key>` */
    public array $rendered = [];

    /** @var list<string> the prescriptions whose content was asked */
    public array $contentRequests = [];

    /** @var list<string> `<since Y-m-d>:<after|->:<limit>` */
    public array $pages = [];

    public function publication(string $clinicId, string $prescriptionId): ?PrescriptionPublication
    {
        return $this->publications[$prescriptionId] ?? null;
    }

    public function renderFile(string $clinicId, string $prescriptionId, string $storageKey): void
    {
        $this->rendered[] = $clinicId . ':' . $prescriptionId . ':' . $storageKey;
    }

    public function states(string $clinicId, array $prescriptionIds): array
    {
        return array_intersect_key($this->states, array_flip($prescriptionIds));
    }

    public function content(string $clinicId, string $prescriptionId): ?PrescriptionContent
    {
        $this->contentRequests[] = $prescriptionId;

        return $this->contents[$prescriptionId] ?? null;
    }

    public function publishableSince(\DateTimeImmutable $issuedFrom, ?string $afterId, int $limit): array
    {
        $this->pages[] = $issuedFrom->format('Y-m-d') . ':' . ($afterId ?? '-') . ':' . $limit;

        return SourcePages::after($this->publishable, $afterId, $limit);
    }
}
