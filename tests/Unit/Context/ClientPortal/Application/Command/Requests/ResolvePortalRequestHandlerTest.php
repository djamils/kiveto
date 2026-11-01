<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Application\Command\Requests;

use App\Context\ClientPortal\Application\Command\ResolvePortalRequest\ResolvePortalRequest;
use App\Context\ClientPortal\Application\Port\PrescriptionPublication;
use App\Context\ClientPortal\Domain\Exception\InvalidPortalRequestException;
use App\Context\ClientPortal\Domain\Exception\PortalRequestAlreadyResolvedException;
use App\Context\ClientPortal\Domain\Exception\PortalRequestNotFoundException;
use App\Context\ClientPortal\Domain\Request\ClosureReason;
use App\Context\ClientPortal\Domain\Request\RequestStatus;
use App\Tests\Unit\Context\ClientPortal\Support\PetWorld as P;
use App\Tests\Unit\Context\ClientPortal\Support\Requests;
use App\Tests\Unit\Context\ClientPortal\Support\RequestWorld as W;
use PHPUnit\Framework\TestCase;

/** AC 69, 230, 231, 238: the clinic's answer and the owner's message. */
final class ResolvePortalRequestHandlerTest extends TestCase
{
    private const string CHANGE  = '01960000-0000-7000-8000-0000000e0001';
    private const string RENEWAL = '01960000-0000-7000-8000-0000000e0002';

    public function testAProcessedRenewalLinksItsPrescriptionAndTellsTheOwnerByEmail(): void
    {
        $world = new W();
        $world->contact();
        $world->renewals->save(Requests::renewal(self::RENEWAL, W::PRESCRIPTION));
        $world->pets->documents->prescriptions->publications[W::RESULTING] = new PrescriptionPublication(W::RESULTING, P::CLIENT, 'ORD-2', Requests::utc('2026-10-28 10:00:00'), 'c-2', P::PET, 'Dr Claire Lambert');

        $status = $world->resolve()(new ResolvePortalRequest(P::CLINIC, self::RENEWAL, 'done', W::VET, 'Dr Claire Lambert', 'Ordonnance prête à l\'accueil.', W::RESULTING));

        $request = $world->renewals->findById(P::CLINIC, self::RENEWAL);
        self::assertSame(['done', RequestStatus::DONE, 'Dr Claire Lambert', W::RESULTING, '01960000-0000-7000-8000-0000000e0001', false], [
            $status, $request?->status(), $request?->resolution()?->resolvedByName(), $request?->resultingPrescriptionId(), $request?->notificationMessageId(), $request?->isClientUnreachable(),
        ]);
        [$message] = $world->pets->documents->fakes->resolutionNotices;
        self::assertSame(
            [self::RENEWAL, P::CLINIC, P::CLIENT, 'email', 'sophie@example.com', 'Sophie Martin', 'Moka', 'Votre demande de renouvellement d\'ordonnance pour Moka a été traitée.', 'Ordonnance prête à l\'accueil.', null],
            [$message->requestId, $message->clinicId, $message->clientId, $message->channel, $message->recipientAddress, $message->recipientName, $message->petName, $message->sentence, $message->staffMessage, $message->fallbackEmail],
        );
    }

    public function testARefusedChangeTellsTheOwnerBySmsWithTheEmailAsFallback(): void
    {
        $world = new W();
        $world->contact(channel: 'sms');
        $world->changes->save(Requests::change(self::CHANGE));

        self::assertSame('rejected', $world->resolve()(new ResolvePortalRequest(P::CLINIC, self::CHANGE, 'rejected', W::ASV, 'Hugo ASV')));

        [$message] = $world->pets->documents->fakes->resolutionNotices;
        self::assertSame(['sms', '+33612345678', 'Votre demande de modification pour Moka n\'a pas pu être acceptée.', '', 'sophie@example.com'], [
            $message->channel, $message->recipientAddress, $message->sentence, $message->staffMessage, $message->fallbackEmail,
        ]);
    }

    public function testARefusedRenewalLinksNothing(): void
    {
        $world = new W();
        $world->contact();
        $world->renewals->save(Requests::renewal(self::RENEWAL, W::PRESCRIPTION));

        self::assertSame('rejected', $world->resolve()(new ResolvePortalRequest(P::CLINIC, self::RENEWAL, 'rejected', W::VET, 'Dr Claire Lambert', 'Venez en consultation.')));
        self::assertNull($world->renewals->findById(P::CLINIC, self::RENEWAL)?->resultingPrescriptionId());
        self::assertSame('Votre demande de renouvellement d\'ordonnance pour Moka n\'a pas pu être acceptée.', $world->pets->documents->fakes->resolutionNotices[0]->sentence);
    }

    public function testAnUnreachableClientIsFlaggedAndNothingIsSent(): void
    {
        $world = new W();
        $world->changes->save(Requests::change(self::CHANGE));
        $world->pets->fakes->pets = [];

        $world->resolve()(new ResolvePortalRequest(P::CLINIC, self::CHANGE, 'done', W::ASV, 'Hugo ASV'));

        self::assertTrue($world->changes->findById(P::CLINIC, self::CHANGE)?->isClientUnreachable());
        self::assertSame([], $world->pets->documents->fakes->resolutionNotices);
        self::assertCount(1, $world->logger->cleanLogs());

        // A client whose channel has no address is just as unreachable
        $world = new W();
        $world->contact(channel: 'sms', mobile: null);
        $world->changes->save(Requests::change(self::CHANGE));
        $world->resolve()(new ResolvePortalRequest(P::CLINIC, self::CHANGE, 'done', W::ASV, 'Hugo ASV'));
        self::assertTrue($world->changes->findById(P::CLINIC, self::CHANGE)?->isClientUnreachable());
        self::assertCount(1, $world->logger->cleanLogs());
    }

    public function testTheSecondAnswerIsRefusedWithTheNameOfTheFirst(): void
    {
        $world = new W();
        $world->contact();
        $world->changes->save(Requests::change(self::CHANGE));
        $world->resolve()(new ResolvePortalRequest(P::CLINIC, self::CHANGE, 'done', W::ASV, 'Hugo ASV'));

        try {
            $world->resolve()(new ResolvePortalRequest(P::CLINIC, self::CHANGE, 'rejected', W::VET, 'Dr Claire Lambert'));
            self::fail('The second answer should be refused.');
        } catch (PortalRequestAlreadyResolvedException $e) {
            self::assertSame('Hugo ASV', $e->resolvedByName);
        }

        self::assertCount(1, $world->pets->documents->fakes->resolutionNotices, 'exactly one message (AC 230)');
    }

    public function testARequestOfAnArchivedOrDeceasedPetIsClosedInsteadAndNothingIsSent(): void
    {
        foreach ([[true, 'alive', ClosureReason::PET_ARCHIVED], [false, 'deceased', ClosureReason::PET_DECEASED]] as [$archived, $life, $reason]) {
            $world = new W();
            $world->contact();
            $world->changes->save(Requests::change(self::CHANGE));
            $world->petState(P::PET, $life, $archived);

            self::assertSame('closed', $world->resolve()(new ResolvePortalRequest(P::CLINIC, self::CHANGE, 'done', W::ASV, 'Hugo ASV')));
            self::assertSame($reason, $world->changes->findById(P::CLINIC, self::CHANGE)?->closedReason());
            self::assertSame([], $world->pets->documents->fakes->resolutionNotices);
        }

        // Already closed: the guard answers
        $this->expectException(PortalRequestAlreadyResolvedException::class);
        $world->resolve()(new ResolvePortalRequest(P::CLINIC, self::CHANGE, 'done', W::ASV, 'Hugo ASV'));
    }

    public function testARenewalOfAnArchivedPetIsClosedToo(): void
    {
        $world = new W();
        $world->renewals->save(Requests::renewal(self::RENEWAL, W::PRESCRIPTION));
        $world->petState(P::PET, archived: true);

        self::assertSame('closed', $world->resolve()(new ResolvePortalRequest(P::CLINIC, self::RENEWAL, 'done', W::VET, 'Dr Claire Lambert')));
        self::assertSame(ClosureReason::PET_ARCHIVED, $world->renewals->findById(P::CLINIC, self::RENEWAL)?->closedReason());
    }

    public function testTheResultingPrescriptionMustBeAnIssuedOneOfThePetIssuedAfterTheRequest(): void
    {
        $issued = static fn (string $animal, string $at): PrescriptionPublication => new PrescriptionPublication(W::RESULTING, P::CLIENT, 'ORD-2', Requests::utc($at), 'c-2', $animal, 'Dr Claire Lambert');

        foreach ([null, $issued(P::PET2, '2026-10-28 10:00:00'), $issued(P::PET, '2026-10-28 08:00:00')] as $publication) {
            $world = new W();
            $world->renewals->save(Requests::renewal(self::RENEWAL, W::PRESCRIPTION));

            if (null !== $publication) {
                $world->pets->documents->prescriptions->publications[W::RESULTING] = $publication;
            }

            try {
                $world->resolve()(new ResolvePortalRequest(P::CLINIC, self::RENEWAL, 'done', W::VET, 'Dr Claire Lambert', null, W::RESULTING));
                self::fail('The prescription should be refused.');
            } catch (InvalidPortalRequestException $e) {
                self::assertSame('resultingPrescriptionId', $e->propertyPath);
            }
        }
    }

    public function testAChangeRequestLinksNoPrescription(): void
    {
        $world = new W();
        $world->changes->save(Requests::change(self::CHANGE));

        $this->expectException(\InvalidArgumentException::class);
        $world->resolve()(new ResolvePortalRequest(P::CLINIC, self::CHANGE, 'done', W::ASV, 'Hugo ASV', null, W::RESULTING));
    }

    public function testAnUnknownOutcomeIsRefused(): void
    {
        $world = new W();
        $world->changes->save(Requests::change(self::CHANGE));

        $this->expectException(\InvalidArgumentException::class);
        $world->resolve()(new ResolvePortalRequest(P::CLINIC, self::CHANGE, 'maybe', W::ASV, 'Hugo ASV'));
    }

    public function testARequestOfAnotherClinicOrAQuestionnaireIsNotFound(): void
    {
        $world = new W();
        $world->changes->save(Requests::change(self::CHANGE, clinicId: '01960000-0000-7000-8000-00000000c009'));
        $world->questionnaires->save(Requests::questionnaire(self::RENEWAL, W::APPOINTMENT));

        foreach ([self::CHANGE, self::RENEWAL] as $id) {
            try {
                $world->resolve()(new ResolvePortalRequest(P::CLINIC, $id, 'done', W::ASV, 'Hugo ASV'));
                self::fail('The request should not be found.');
            } catch (PortalRequestNotFoundException $e) {
                self::assertSame($id, $e->requestId);
            }
        }
    }
}
