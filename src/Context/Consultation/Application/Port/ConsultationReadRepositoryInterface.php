<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Port;

use App\Context\Consultation\Application\Query\GetConsultationDetails\ConsultationDetailsDTO;
use App\Context\Consultation\Application\Query\ListConsultationRegister\ConsultationRegisterCriteria;
use App\Context\Consultation\Application\Query\ListConsultationRegister\ConsultationRegisterRow;
use App\Context\Consultation\Application\Query\SearchConsultations\ConsultationListRow;
use App\Context\Consultation\Application\Query\SearchConsultations\SearchConsultationsCriteria;
use App\Context\Consultation\Domain\ValueObject\AdmissionId;
use App\Context\Consultation\Domain\ValueObject\ClinicId;
use App\Context\Consultation\Domain\ValueObject\ConsultationId;

interface ConsultationReadRepositoryInterface
{
    /**
     * Finds a consultation with all its details.
     *
     * @throws \DomainException if consultation not found or does not belong to $clinicId
     */
    public function findById(ConsultationId $consultationId, ClinicId $clinicId): ConsultationDetailsDTO;

    /**
     * Lists the clinic's consultations for a set of patient ids, most recent first.
     *
     * @param list<string> $patientIds
     *
     * @return list<array{
     *     consultationId: string,
     *     startedAtUtc: string,
     *     closedAtUtc: ?string,
     *     status: string,
     *     chiefComplaint: ?string,
     *     summary: ?string,
     *     weightKg: ?string
     * }>
     */
    public function listForPatients(
        array $patientIds,
        ClinicId $clinicId,
        ?ConsultationId $excludeId = null,
    ): array;

    /**
     * Whether the admission still has a consultation running, ignoring one.
     *
     * A visit can carry several consultations; it is only over for the
     * practitioner once the last of them is closed.
     */
    public function hasOpenConsultationForAdmission(
        AdmissionId $admissionId,
        ClinicId $clinicId,
        ConsultationId $excluding,
    ): bool;

    /**
     * Billing totals per visit, broken down by tax category and split by the
     * mode the line prices were entered in (pre-tax or all taxes included).
     *
     * Same shape as the list rows and for the same reason: this repository
     * stays bus-free, so the VAT is applied by the handler.
     *
     * @param list<string> $admissionIds
     *
     * @return array<string, array{htByTaxCategory: array<string, int>, ttcByTaxCategory: array<string, int>, currency: string}>
     */
    public function totalsForAdmissions(ClinicId $clinicId, array $admissionIds): array;

    /**
     * Every billing line of every consultation of a visit, oldest first.
     *
     * The Billing BC snapshots these into an invoice; the rows stay a plain
     * shape so no Consultation type leaks across the boundary.
     *
     * @return list<array{
     *     consultationId: string,
     *     sourceLineId: string,
     *     label: string,
     *     code: ?string,
     *     quantity: string,
     *     unitPriceMinorUnits: int,
     *     currency: string,
     *     unitPriceMode: string,
     *     taxCategoryCode: ?string
     * }>
     */
    public function billingLinesForAdmission(ClinicId $clinicId, AdmissionId $admissionId): array;

    /**
     * The medicines recorded on one consultation, in the order they were added.
     *
     * The Prescription BC snapshots these into an ordonnance; the rows stay a
     * plain shape so no Consultation type leaks across the boundary.
     *
     * @return list<array{
     *     sourceLineId: string,
     *     articleId: ?string,
     *     code: ?string,
     *     label: string,
     *     dose: ?string,
     *     frequency: ?string,
     *     durationDays: ?int,
     *     route: ?string,
     *     quantity: string
     * }>
     */
    public function prescriptionLinesForConsultation(ClinicId $clinicId, ConsultationId $consultationId): array;

    /**
     * One page of the clinic-wide consultation list, plus the unpaginated total.
     *
     * @return array{items: list<ConsultationListRow>, total: int}
     */
    public function search(ClinicId $clinicId, SearchConsultationsCriteria $criteria): array;

    /**
     * One page of the clinic's register of consultations, plus the
     * unpaginated total.
     *
     * @return array{items: list<ConsultationRegisterRow>, total: int}
     */
    public function register(ClinicId $clinicId, ConsultationRegisterCriteria $criteria): array;

    /**
     * Counters of the register's header: consultations in progress, to close
     * (open since a previous day or whose visit is over), started today, and
     * recorded by the clinic at all.
     *
     * @param list<string> $departedAdmissionIds
     *
     * @return array{inProgress: int, toClose: int, today: int, recorded: int}
     */
    public function registerCounters(ClinicId $clinicId, \DateTimeImmutable $dayStartUtc, array $departedAdmissionIds): array;

    /**
     * Visits of the clinic's consultations still open — a short list, since a
     * consultation only stays open while it is being written.
     *
     * @return list<string>
     */
    public function openConsultationAdmissionIds(ClinicId $clinicId): array;
}
