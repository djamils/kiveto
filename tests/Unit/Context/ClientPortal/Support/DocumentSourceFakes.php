<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Support;

use App\Context\ClientPortal\Application\Port\ConsultationDocumentsInterface;
use App\Context\ClientPortal\Application\Port\DocumentFileJobsInterface;
use App\Context\ClientPortal\Application\Port\ReportShareability;
use App\Context\ClientPortal\Application\Port\VisitPatientsInterface;
use App\Context\ClientPortal\Domain\Document\DocumentReadRepositoryInterface;

/**
 * Hand-written doubles of the consultation and visit ports, the file jobs
 * and the read marks: answers from public maps, calls recorded in order.
 */
final class DocumentSourceFakes implements
    ConsultationDocumentsInterface,
    VisitPatientsInterface,
    DocumentFileJobsInterface,
    DocumentReadRepositoryInterface
{
    /** @var array<string, ReportShareability> by consultation id */
    public array $shareabilities = [];

    /** @var array<string, list<string>> by admission id */
    public array $consultations = [];

    /** @var array<string, string> by admission id */
    public array $patients = [];

    /** @var array<string, string> by patient id */
    public array $animals = [];

    public ?\Throwable $renderFailure = null;

    /** @var list<string> `report:<clinic>:<consultation>:<key>` */
    public array $rendered = [];

    /** @var list<string> `store:<document>:<version>` or `delete:<key>` */
    public array $jobs = [];

    /** @var list<string> `read:<document>:<user>:<Y-m-d H:i>` or `forget:<document>` */
    public array $reads = [];

    public function shareability(string $clinicId, string $consultationId): ?ReportShareability
    {
        return $this->shareabilities[$consultationId] ?? null;
    }

    public function renderReport(string $clinicId, string $consultationId, string $storageKey): void
    {
        if (null !== $this->renderFailure) {
            throw $this->renderFailure;
        }

        $this->rendered[] = 'report:' . $clinicId . ':' . $consultationId . ':' . $storageKey;
    }

    public function consultationsOfAdmission(string $clinicId, string $admissionId): array
    {
        return $this->consultations[$admissionId] ?? [];
    }

    public function patientOfAdmission(string $clinicId, string $admissionId): ?string
    {
        return $this->patients[$admissionId] ?? null;
    }

    public function animalOfPatient(string $clinicId, string $patientId): ?string
    {
        return $this->animals[$patientId] ?? null;
    }

    public function storeFile(string $documentId, int $version): void
    {
        $this->jobs[] = 'store:' . $documentId . ':' . $version;
    }

    public function deleteFileLater(string $storageKey): void
    {
        $this->jobs[] = 'delete:' . $storageKey;
    }

    public function markRead(string $documentId, string $userId, \DateTimeImmutable $at): void
    {
        $this->reads[] = 'read:' . $documentId . ':' . $userId . ':' . $at->format('Y-m-d H:i');
    }

    public function forgetReads(string $documentId): void
    {
        $this->reads[] = 'forget:' . $documentId;
    }
}
