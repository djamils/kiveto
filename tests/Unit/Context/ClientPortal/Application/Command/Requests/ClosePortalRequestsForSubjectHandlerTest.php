<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Application\Command\Requests;

use App\Context\ClientPortal\Application\Command\ClosePortalRequestsForSubject\ClosePortalRequestsForSubject;
use App\Context\ClientPortal\Application\Port\ErasedOwner;
use App\Context\ClientPortal\Application\Service\PortalRequestAccountErasure;
use App\Context\ClientPortal\Domain\Request\ChangeField;
use App\Context\ClientPortal\Domain\Request\ClosureReason;
use App\Context\ClientPortal\Domain\Request\RequestResolution;
use App\Context\ClientPortal\Domain\Request\RequestStatus;
use App\Context\ClientPortal\Domain\Request\RequestSubject;
use App\Tests\Unit\Context\ClientPortal\Support\PetWorld as P;
use App\Tests\Unit\Context\ClientPortal\Support\Requests;
use App\Tests\Unit\Context\ClientPortal\Support\RequestWorld as W;
use PHPUnit\Framework\TestCase;

/** AC 238: closures without message, and the account deletion's erasure. */
final class ClosePortalRequestsForSubjectHandlerTest extends TestCase
{
    private const string Q1 = '01960000-0000-7000-8000-0000000e0001';
    private const string C1 = '01960000-0000-7000-8000-0000000e0002';
    private const string C2 = '01960000-0000-7000-8000-0000000e0003';
    private const string R1 = '01960000-0000-7000-8000-0000000e0004';
    private const string R2 = '01960000-0000-7000-8000-0000000e0005';

    public function testAnArchivedClientClosesItsOpenRequestsOnly(): void
    {
        $world = $this->world();

        self::assertSame(3, $world->close()(new ClosePortalRequestsForSubject(RequestSubject::CLIENT, P::CLIENT, ClosureReason::CLIENT_ARCHIVED, P::CLINIC)));

        self::assertSame(
            [RequestStatus::CLOSED, RequestStatus::CLOSED, RequestStatus::SUBMITTED, RequestStatus::CLOSED, RequestStatus::DONE],
            [
                $world->questionnaires->findByAppointment(P::CLINIC, W::APPOINTMENT)?->status(),
                $world->changes->findById(P::CLINIC, self::C1)?->status(),
                $world->changes->findById(P::CLINIC, self::C2)?->status(),
                $world->renewals->findById(P::CLINIC, self::R1)?->status(),
                $world->renewals->findById(P::CLINIC, self::R2)?->status(),
            ],
        );
        self::assertSame(ClosureReason::CLIENT_ARCHIVED, $world->changes->findById(P::CLINIC, self::C1)?->closedReason());
        self::assertSame('2026-10-29 09:00:00', $world->renewals->findById(P::CLINIC, self::R1)?->closedAt()?->format('Y-m-d H:i:s'));

        // Idempotent
        self::assertSame(0, $world->close()(new ClosePortalRequestsForSubject(RequestSubject::CLIENT, P::CLIENT, ClosureReason::CLIENT_ARCHIVED, P::CLINIC)));
    }

    public function testEachSubjectClosesWhatHangsOnIt(): void
    {
        $world = $this->world();
        self::assertSame(1, $world->close()(new ClosePortalRequestsForSubject(RequestSubject::PRESCRIPTION, W::PRESCRIPTION, ClosureReason::PRESCRIPTION_CANCELLED, P::CLINIC)));
        self::assertSame(1, $world->close()(new ClosePortalRequestsForSubject(RequestSubject::APPOINTMENT, W::APPOINTMENT, ClosureReason::APPOINTMENT_DECLINED, P::CLINIC)));
        self::assertSame(1, $world->close()(new ClosePortalRequestsForSubject(RequestSubject::PET, P::PET, ClosureReason::PET_DECEASED, P::CLINIC)));
        self::assertSame(1, $world->close()(new ClosePortalRequestsForSubject(RequestSubject::PET, P::PET2, ClosureReason::PET_ARCHIVED, P::CLINIC)));
    }

    public function testTheAccountDeletionClosesTheAccountsRequestsAndErasesTheirText(): void
    {
        $world = $this->world();

        new PortalRequestAccountErasure($world->closer())->erase(P::USER, new ErasedOwner('+33612345678', null));

        $questionnaire = $world->questionnaires->findByAppointment(P::CLINIC, W::APPOINTMENT);
        $change        = $world->changes->findById(P::CLINIC, self::C1);
        $renewal       = $world->renewals->findById(P::CLINIC, self::R2);
        self::assertSame([RequestStatus::CLOSED, ClosureReason::ACCOUNT_DELETED, null, null], [$questionnaire?->status(), $questionnaire?->closedReason(), $questionnaire?->answers()->notes(), $questionnaire?->answers()->ongoingTreatmentDetails()]);
        self::assertSame([RequestStatus::CLOSED, ClosureReason::ACCOUNT_DELETED, null], [$change?->status(), $change?->closedReason(), $change?->change()->note()]);
        // An answered request stays answered, its owner's text erased
        self::assertSame([RequestStatus::DONE, null, 'OK'], [$renewal?->status(), $renewal?->note(), $renewal?->resolution()?->message()]);
    }

    private function world(): W
    {
        $world = new W();
        $world->questionnaires->save(Requests::questionnaire(self::Q1, W::APPOINTMENT));
        $world->changes->save(Requests::change(self::C1));
        $world->changes->save(Requests::change(self::C2, petId: P::PET2, field: ChangeField::NAME, value: 'Rexy', userId: '01960000-0000-7000-8000-0000000c0099', clientId: P::CLIENT2));
        $world->renewals->save(Requests::renewal(self::R1, W::PRESCRIPTION, petId: P::PET2));
        $done = Requests::renewal(self::R2, W::RESULTING);
        $done->resolve(RequestResolution::of(RequestStatus::DONE, W::VET, 'Dr Claire Lambert', 'OK', Requests::utc('2026-10-28 10:00:00')));
        $world->renewals->save($done);

        return $world;
    }
}
