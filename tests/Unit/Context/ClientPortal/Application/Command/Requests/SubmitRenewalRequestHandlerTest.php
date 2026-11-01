<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Application\Command\Requests;

use App\Context\ClientPortal\Application\Command\SubmitRenewalRequest\SubmitRenewalRequest;
use App\Context\ClientPortal\Domain\Exception\InvalidPortalRequestException;
use App\Context\ClientPortal\Domain\Exception\PrescriptionNotFoundException;
use App\Context\ClientPortal\Domain\Exception\PrescriptionNotRenewableException;
use App\Context\ClientPortal\Domain\Request\PetCondition;
use App\Context\ClientPortal\Domain\Request\RequestResolution;
use App\Context\ClientPortal\Domain\Request\RequestStatus;
use App\Tests\Unit\Context\ClientPortal\Support\PetWorld as P;
use App\Tests\Unit\Context\ClientPortal\Support\Requests;
use App\Tests\Unit\Context\ClientPortal\Support\RequestWorld as W;
use PHPUnit\Framework\TestCase;

/** AC 68, 233, 237, 243: S6c and the single open request per prescription. */
final class SubmitRenewalRequestHandlerTest extends TestCase
{
    public function testARenewableExpiredPrescriptionOfAnAlivePetIsRequestedAndAnnouncedToItsPrescriber(): void
    {
        $world = new W();
        $world->prescription();
        $world->contact();

        $id = $world->submitRenewal()(new SubmitRenewalRequest(P::USER, P::CLINIC, W::PRESCRIPTION, 'adverse_effects', 'Vomit'));

        $request = $world->renewals->findById(P::CLINIC, $id);
        self::assertNotNull($request);
        self::assertSame([P::CLIENT, P::USER, W::PRESCRIPTION, P::PET, PetCondition::ADVERSE_EFFECTS, 'Vomit', RequestStatus::SUBMITTED], [
            $request->clientId(), $request->userId(), $request->prescriptionId(), $request->petId(), $request->petCondition(), $request->note(), $request->status(),
        ]);
        self::assertSame([[
            'clinicId'  => P::CLINIC,
            'userIds'   => [W::VET],
            'requestId' => $id,
            'title'     => 'Demande de renouvellement — Moka',
            'detail'    => 'Sophie Martin · Effets indésirables signalés',
        ]], $world->fakes->bells);
    }

    public function testAPrescriberNoLongerVeterinarianLetsEveryVeterinarianKnow(): void
    {
        $world = new W();
        $world->prescription(prescriber: '01960000-0000-7000-8000-00000000d199');

        $world->submitRenewal()(new SubmitRenewalRequest(P::USER, P::CLINIC, W::PRESCRIPTION, 'improved'));

        self::assertSame([[W::VET, W::OTHER_VET], 'Amélioration'], [$world->fakes->bells[0]['userIds'], $world->fakes->bells[0]['detail']]);
    }

    public function testASecondSubmitAnswersTheOpenRequest(): void
    {
        $world = new W();
        $world->prescription();
        $first = $world->submitRenewal()(new SubmitRenewalRequest(P::USER, P::CLINIC, W::PRESCRIPTION, 'improved'));

        // Even with an unknown condition: the open request is the answer
        self::assertSame($first, $world->submitRenewal()(new SubmitRenewalRequest(P::USER, P::CLINIC, W::PRESCRIPTION, 'whatever')));
        self::assertCount(1, $world->renewals->requests);
        self::assertCount(1, $world->fakes->bells);
    }

    public function testAConcurrentSubmitThatWonIsTheAnswer(): void
    {
        $world = new W();
        $world->prescription();
        $world->renewals->concurrentWinner = Requests::renewal('01960000-0000-7000-8000-0000000e0042', W::PRESCRIPTION);

        self::assertSame('01960000-0000-7000-8000-0000000e0042', $world->submitRenewal()(new SubmitRenewalRequest(P::USER, P::CLINIC, W::PRESCRIPTION, 'improved')));
        self::assertSame([], $world->fakes->bells);
    }

    public function testAPrescriptionNotListedForTheAccountIsNotFound(): void
    {
        $this->expectException(PrescriptionNotFoundException::class);
        new W()->submitRenewal()(new SubmitRenewalRequest(P::USER, P::CLINIC, W::PRESCRIPTION, 'improved'));
    }

    public function testACancelledPrescriptionOfTheAccountIsNotRenewable(): void
    {
        $world                                = new W();
        $world->pets->model->withdrawnSources = ['prescription/' . W::PRESCRIPTION];

        $this->assertNotRenewable($world, PrescriptionNotRenewableException::NOT_RENEWABLE);
    }

    public function testAControlledSubstanceOrAPrescriptionWithoutPetIsNotRenewable(): void
    {
        $world = new W();
        $world->prescription(renewable: false);
        $this->assertNotRenewable($world, PrescriptionNotRenewableException::NOT_RENEWABLE);

        $world = new W();
        $world->prescription(animalId: null);
        $this->assertNotRenewable($world, PrescriptionNotRenewableException::NOT_RENEWABLE);

        $world = new W();
        $world->prescription();
        unset($world->pets->documents->prescriptions->states[W::PRESCRIPTION]);
        $this->assertNotRenewable($world, PrescriptionNotRenewableException::NOT_RENEWABLE);
    }

    public function testAPetNotAliveIsNotRenewable(): void
    {
        foreach ([['deceased', false], ['missing', false], ['alive', true]] as [$life, $archived]) {
            $world = new W();
            $world->prescription();
            $world->petState(P::PET, $life, $archived);
            $this->assertNotRenewable($world, PrescriptionNotRenewableException::PET_NOT_ALIVE);
        }

        $world = new W();
        $world->prescription(animalId: '01960000-0000-7000-8000-0000000a0099');
        $this->assertNotRenewable($world, PrescriptionNotRenewableException::PET_NOT_ALIVE);
    }

    public function testARefusalOfLessThanSevenDaysKeepsANewRequestOut(): void
    {
        $world = new W();
        $world->prescription();
        $rejected = Requests::renewal('01960000-0000-7000-8000-0000000e0001', W::PRESCRIPTION, at: '2026-10-20 08:00:00');
        $rejected->resolve(RequestResolution::of(RequestStatus::REJECTED, W::VET, 'Dr Claire Lambert', null, Requests::utc('2026-10-22 09:00:01')));
        $world->renewals->save($rejected);

        $this->assertNotRenewable($world, PrescriptionNotRenewableException::RECENTLY_REJECTED);

        // Seven days after the refusal, it is accepted (AC 237)
        $world->pets->documents->clock->now = Requests::utc('2026-10-29 09:00:01');
        self::assertNotSame('01960000-0000-7000-8000-0000000e0001', $world->submitRenewal()(new SubmitRenewalRequest(P::USER, P::CLINIC, W::PRESCRIPTION, 'improved')));
    }

    public function testAnUnknownConditionOrALongNoteIsRefused(): void
    {
        $world = new W();
        $world->prescription();

        foreach ([['sad', null, 'petCondition'], ['improved', str_repeat('a', 501), 'note']] as [$condition, $note, $property]) {
            try {
                $world->submitRenewal()(new SubmitRenewalRequest(P::USER, P::CLINIC, W::PRESCRIPTION, $condition, $note));
                self::fail('The request should be refused.');
            } catch (InvalidPortalRequestException $e) {
                self::assertSame($property, $e->propertyPath);
            }
        }
    }

    private function assertNotRenewable(W $world, string $reason): void
    {
        try {
            $world->submitRenewal()(new SubmitRenewalRequest(P::USER, P::CLINIC, W::PRESCRIPTION, 'improved'));
            self::fail('The prescription should not be renewable.');
        } catch (PrescriptionNotRenewableException $e) {
            self::assertSame([W::PRESCRIPTION, $reason], [$e->prescriptionId, $e->reason]);
        }
    }
}
