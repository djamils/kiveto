<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Consultation\Application\Query\GetConsultationPortalSummaries;

use App\Context\Consultation\Application\Query\GetConsultationPortalSummaries\ConsultationPortalSummary;
use App\Context\Consultation\Application\Query\GetConsultationPortalSummaries\GetConsultationPortalSummaries;
use App\Context\Consultation\Application\Query\GetConsultationPortalSummaries\GetConsultationPortalSummariesHandler;
use App\Context\Consultation\Domain\Consultation;
use App\Context\Consultation\Domain\Repository\ConsultationRepositoryInterface;
use App\Context\Consultation\Domain\ValueObject\AdmissionId;
use App\Context\Consultation\Domain\ValueObject\ClinicId;
use App\Context\Consultation\Domain\ValueObject\ConsultationId;
use App\Context\Consultation\Domain\ValueObject\PatientId;
use App\Context\Consultation\Domain\ValueObject\UserId;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class GetConsultationPortalSummariesHandlerTest extends KernelTestCase
{
    private const string CLINIC_ID       = '11111111-1111-4111-8111-111111111111';
    private const string OTHER_CLINIC_ID = '22222222-2222-4222-8222-222222222222';
    private const string PATIENT_ID      = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa1';
    private const string USER_ID         = '55555555-5555-4555-8555-555555555555';
    private const string WITH_MOTIFS     = 'cccccccc-cccc-4ccc-8ccc-ccccccccccc1';
    private const string WITH_COMPLAINT  = 'cccccccc-cccc-4ccc-8ccc-ccccccccccc2';
    private const string BARE            = 'cccccccc-cccc-4ccc-8ccc-ccccccccccc3';
    private const string OTHER_CLINIC    = 'cccccccc-cccc-4ccc-8ccc-ccccccccccc4';
    private const string UNKNOWN         = 'cccccccc-cccc-4ccc-8ccc-ccccccccccc9';

    private ConsultationRepositoryInterface $repository;

    private GetConsultationPortalSummariesHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $repository = self::getContainer()->get(ConsultationRepositoryInterface::class);
        \assert($repository instanceof ConsultationRepositoryInterface);
        $this->repository = $repository;

        $handler = self::getContainer()->get(GetConsultationPortalSummariesHandler::class);
        \assert($handler instanceof GetConsultationPortalSummariesHandler);
        $this->handler = $handler;
    }

    public function testSummarisesTheRequestedConsultationsInTheirOrder(): void
    {
        $this->seed();

        $summaries = ($this->handler)(new GetConsultationPortalSummaries(
            clinicId: self::CLINIC_ID,
            consultationIds: [self::BARE, self::WITH_MOTIFS, self::UNKNOWN, self::OTHER_CLINIC, self::WITH_COMPLAINT, self::WITH_MOTIFS],
        ));

        self::assertSame(
            [
                [self::BARE, '2026-04-12T08:00:00+00:00', 'Consultation'],
                [self::WITH_MOTIFS, '2026-04-10T08:00:00+00:00', 'Vaccination et bilan annuel'],
                [self::WITH_COMPLAINT, '2026-04-11T08:00:00+00:00', 'Toux persistante'],
            ],
            array_map(
                static fn (ConsultationPortalSummary $s): array => [$s->consultationId, $s->startedAtUtc->format(\DATE_ATOM), $s->title],
                $summaries,
            ),
        );
        self::assertSame('UTC', $summaries[0]->startedAtUtc->getTimezone()->getName());
    }

    public function testAnOpenConsultationIsSummarisedToo(): void
    {
        $this->seed();

        // The summaries only title the rows the registry lists; the status is
        // the caller's concern.
        $summaries = ($this->handler)(new GetConsultationPortalSummaries(self::CLINIC_ID, [self::WITH_COMPLAINT]));

        self::assertCount(1, $summaries);
        self::assertSame('Toux persistante', $summaries[0]->title);
    }

    public function testEmptyInputReturnsNothing(): void
    {
        self::assertSame([], ($this->handler)(new GetConsultationPortalSummaries(self::CLINIC_ID, [])));
    }

    private function seed(): void
    {
        $withMotifs = $this->start(self::WITH_MOTIFS, self::CLINIC_ID, '2026-04-10 08:00:00');
        $withMotifs->setMotifs(['Vaccination', 'Bilan annuel'], new \DateTimeImmutable('2026-04-10 08:05:00'));
        $withMotifs->recordChiefComplaint('Ignorée, des motifs existent.', new \DateTimeImmutable('2026-04-10 08:06:00'));
        $withMotifs->close(UserId::fromString(self::USER_ID), null, new \DateTimeImmutable('2026-04-10 09:00:00'));
        $this->repository->save($withMotifs);

        $withComplaint = $this->start(self::WITH_COMPLAINT, self::CLINIC_ID, '2026-04-11 08:00:00');
        $withComplaint->recordChiefComplaint('Toux persistante.', new \DateTimeImmutable('2026-04-11 08:05:00'));
        $this->repository->save($withComplaint);

        $bare = $this->start(self::BARE, self::CLINIC_ID, '2026-04-12 08:00:00');
        $this->repository->save($bare);

        $other = $this->start(self::OTHER_CLINIC, self::OTHER_CLINIC_ID, '2026-04-13 08:00:00');
        $other->setMotifs(['Urgence'], new \DateTimeImmutable('2026-04-13 08:05:00'));
        $this->repository->save($other);
    }

    private function start(string $consultationId, string $clinicId, string $startedAt): Consultation
    {
        $consultation = Consultation::startFromAdmission(
            ConsultationId::fromString($consultationId),
            ClinicId::fromString($clinicId),
            AdmissionId::fromString(Uuid::v7()->toRfc4122()),
            PatientId::fromString(self::PATIENT_ID),
            UserId::fromString(self::USER_ID),
            new \DateTimeImmutable($startedAt),
        );

        $this->repository->save($consultation);

        return $consultation;
    }
}
