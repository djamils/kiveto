<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Domain\Request;

use App\Context\ClientPortal\Domain\Exception\InvalidPortalRequestException;
use App\Context\ClientPortal\Domain\Exception\PortalRequestAlreadyResolvedException;
use App\Context\ClientPortal\Domain\Request\ChangeField;
use App\Context\ClientPortal\Domain\Request\ClosureReason;
use App\Context\ClientPortal\Domain\Request\PetChangeRequest;
use App\Context\ClientPortal\Domain\Request\PetCondition;
use App\Context\ClientPortal\Domain\Request\PortalRequestId;
use App\Context\ClientPortal\Domain\Request\PortalRequestKind;
use App\Context\ClientPortal\Domain\Request\PrescriptionRenewalRequest;
use App\Context\ClientPortal\Domain\Request\ProposedChange;
use App\Context\ClientPortal\Domain\Request\RequestResolution;
use App\Context\ClientPortal\Domain\Request\RequestStatus;
use App\Tests\Unit\Context\ClientPortal\Support\PetWorld as W;
use App\Tests\Unit\Context\ClientPortal\Support\Requests;
use PHPUnit\Framework\TestCase;

/** S6a: submitted → done | rejected | closed, guarded in the domain (AC 231). */
final class PortalRequestLifecycleTest extends TestCase
{
    private const string ID           = '01960000-0000-7000-8000-0000000e0001';
    private const string PRESCRIPTION = '01960000-0000-7000-8000-0000000b0001';
    private const string RESULTING    = '01960000-0000-7000-8000-0000000b0002';
    private const string VET          = '01960000-0000-7000-8000-00000000d001';
    private const string MESSAGE      = '01960000-0000-7000-8000-0000000f0001';

    public function testAChangeRequestIsSubmittedWithItsSnapshot(): void
    {
        $request = Requests::change(self::ID);

        self::assertSame(
            [self::ID, W::CLINIC, W::CLIENT, W::USER, W::PET, ChangeField::WEIGHT, '11800', RequestStatus::SUBMITTED, '2026-10-28 08:00:00', '2026-10-28 08:00:00', null, null, null, null, false, true],
            [
                $request->id()->toString(), $request->clinicId(), $request->clientId(), $request->userId(), $request->petId(), $request->change()->field(),
                $request->currentValueAtSubmit(), $request->status(), $request->submittedAt()->format('Y-m-d H:i:s'), $request->updatedAt()->format('Y-m-d H:i:s'),
                $request->resolution(), $request->closedAt(), $request->closedReason(), $request->notificationMessageId(), $request->isClientUnreachable(), $request->isSubmitted(),
            ],
        );
    }

    public function testTheFirstAnswerWinsAndTheSecondNamesWhoAnswered(): void
    {
        $request = Requests::change(self::ID);
        $request->resolve(RequestResolution::of(RequestStatus::DONE, self::VET, 'Dr Claire Lambert', '  Poids noté.  ', Requests::utc('2026-10-29 08:00:00')));

        self::assertSame([RequestStatus::DONE, 'Poids noté.', 'Dr Claire Lambert', self::VET, '2026-10-29 08:00:00', false], [
            $request->status(), $request->resolution()?->message(), $request->resolution()?->resolvedByName(), $request->resolution()?->resolvedByUserId(),
            $request->updatedAt()->format('Y-m-d H:i:s'), $request->isSubmitted(),
        ]);

        try {
            $request->resolve(RequestResolution::of(RequestStatus::REJECTED, 'other', 'Hugo ASV', null, Requests::utc('2026-10-29 08:00:01')));
            self::fail('A second answer should be refused.');
        } catch (PortalRequestAlreadyResolvedException $e) {
            self::assertSame([self::ID, 'done', 'Dr Claire Lambert', 'Demande déjà traitée par Dr Claire Lambert.'], [$e->requestId, $e->status, $e->resolvedByName, $e->getMessage()]);
        }

        // An answered request is never closed
        self::assertFalse($request->close(ClosureReason::CLIENT_ARCHIVED, Requests::utc('2026-10-30 08:00:00')));
        self::assertSame(RequestStatus::DONE, $request->status());
    }

    public function testAClosedRequestIsNeverAnswered(): void
    {
        $request = Requests::renewal(self::ID, self::PRESCRIPTION);

        self::assertTrue($request->close(ClosureReason::PRESCRIPTION_CANCELLED, Requests::utc('2026-10-29 08:00:00')));
        self::assertFalse($request->close(ClosureReason::CLIENT_ARCHIVED, Requests::utc('2026-10-30 08:00:00')));
        self::assertSame([RequestStatus::CLOSED, ClosureReason::PRESCRIPTION_CANCELLED, '2026-10-29 08:00:00'], [$request->status(), $request->closedReason(), $request->closedAt()?->format('Y-m-d H:i:s')]);

        $this->expectExceptionObject(new PortalRequestAlreadyResolvedException(self::ID, 'closed', null));
        $request->resolve(RequestResolution::of(RequestStatus::DONE, self::VET, 'Dr Claire Lambert', null, Requests::utc('2026-10-30 08:00:00')));
    }

    public function testTheNotificationOrTheUnreachableClientIsRecorded(): void
    {
        $request = Requests::change(self::ID);
        $request->markClientUnreachable();
        self::assertTrue($request->isClientUnreachable());

        $request->attachNotification(self::MESSAGE);
        self::assertSame([self::MESSAGE, false], [$request->notificationMessageId(), $request->isClientUnreachable()]);
    }

    public function testAProcessedRenewalMayLinkThePrescriptionWrittenForIt(): void
    {
        $request = Requests::renewal(self::ID, self::PRESCRIPTION, condition: PetCondition::ADVERSE_EFFECTS, note: '  Vomit  ');
        self::assertSame([self::PRESCRIPTION, W::PET, PetCondition::ADVERSE_EFFECTS, 'Vomit', null], [$request->prescriptionId(), $request->petId(), $request->petCondition(), $request->note(), $request->resultingPrescriptionId()]);

        $request->resolve(RequestResolution::of(RequestStatus::DONE, self::VET, 'Dr Claire Lambert', null, Requests::utc('2026-10-29 08:00:00')), self::RESULTING);

        self::assertSame([RequestStatus::DONE, self::RESULTING], [$request->status(), $request->resultingPrescriptionId()]);
    }

    public function testARefusalLinksNoPrescription(): void
    {
        $request = Requests::renewal(self::ID, self::PRESCRIPTION);

        $this->expectException(\InvalidArgumentException::class);
        $request->resolve(RequestResolution::of(RequestStatus::REJECTED, self::VET, 'Dr Claire Lambert', null, Requests::utc('2026-10-29 08:00:00')), self::RESULTING);
    }

    public function testARefusalBlocksANewRenewalForSevenDays(): void
    {
        $request = Requests::renewal(self::ID, self::PRESCRIPTION);
        self::assertFalse($request->blocksRenewalAt(Requests::utc('2026-10-29 08:00:00')), 'still submitted');

        $request->resolve(RequestResolution::of(RequestStatus::REJECTED, self::VET, 'Dr Claire Lambert', null, Requests::utc('2026-10-29 08:00:00')));

        self::assertTrue($request->blocksRenewalAt(Requests::utc('2026-11-05 07:59:59')));
        self::assertFalse($request->blocksRenewalAt(Requests::utc('2026-11-05 08:00:00')));

        $done = Requests::renewal(self::ID, self::PRESCRIPTION);
        $done->resolve(RequestResolution::of(RequestStatus::DONE, self::VET, 'Dr Claire Lambert', null, Requests::utc('2026-10-29 08:00:00')));
        self::assertFalse($done->blocksRenewalAt(Requests::utc('2026-10-30 08:00:00')));
    }

    public function testARenewalNoteIsAtMost500Characters(): void
    {
        $this->expectExceptionObject(new InvalidPortalRequestException('note', 'La note ne doit pas dépasser 500 caractères.'));

        Requests::renewal(self::ID, self::PRESCRIPTION, note: str_repeat('a', 501));
    }

    public function testAnAccountDeletionErasesTheOwnersText(): void
    {
        $change = Requests::change(self::ID, field: ChangeField::OTHER, value: 'Adresse', note: 'Rue des Lilas');
        $change->eraseFreeText();
        $renewal = Requests::renewal(self::ID, self::PRESCRIPTION);
        $renewal->eraseFreeText();

        self::assertSame(['', null, null], [$change->change()->proposedValue(), $change->change()->note(), $renewal->note()]);
    }

    public function testStoredRequestsAreReconstitutedAsIs(): void
    {
        $resolution = RequestResolution::reconstitute(RequestStatus::REJECTED, Requests::utc('2026-10-29 08:00:00'), self::VET, 'Dr Claire Lambert', 'Non');
        $change     = PetChangeRequest::reconstitute(
            PortalRequestId::fromString(self::ID),
            W::CLINIC,
            W::CLIENT,
            W::USER,
            W::PET,
            ProposedChange::reconstitute(ChangeField::NAME, 'Moka', ['name' => 'Moka'], null),
            'Mokka',
            RequestStatus::REJECTED,
            Requests::utc('2026-10-28 08:00:00'),
            Requests::utc('2026-10-29 08:00:00'),
            $resolution,
            null,
            null,
            self::MESSAGE,
            false,
        );
        $renewal = PrescriptionRenewalRequest::reconstitute(
            PortalRequestId::fromString(self::ID),
            W::CLINIC,
            W::CLIENT,
            W::USER,
            self::PRESCRIPTION,
            W::PET,
            PetCondition::NO_IMPROVEMENT,
            null,
            null,
            RequestStatus::CLOSED,
            Requests::utc('2026-10-28 08:00:00'),
            Requests::utc('2026-10-29 08:00:00'),
            null,
            Requests::utc('2026-10-29 08:00:00'),
            ClosureReason::PET_DECEASED,
            null,
            true,
        );

        self::assertSame([RequestStatus::REJECTED, 'Non', 'Mokka', self::MESSAGE], [$change->status(), $change->resolution()?->message(), $change->currentValueAtSubmit(), $change->notificationMessageId()]);
        self::assertSame([RequestStatus::CLOSED, ClosureReason::PET_DECEASED, true], [$renewal->status(), $renewal->closedReason(), $renewal->isClientUnreachable()]);
    }

    public function testAResolutionIsDoneOrRejectedWithAMessageOfAtMost280Characters(): void
    {
        $now = Requests::utc('2026-10-29 08:00:00');
        self::assertNull(RequestResolution::of(RequestStatus::DONE, self::VET, 'Dr Claire Lambert', '   ', $now)->message());
        self::assertSame(str_repeat('é', 280), RequestResolution::of(RequestStatus::DONE, self::VET, 'Dr Claire Lambert', str_repeat('é', 280), $now)->message());

        try {
            RequestResolution::of(RequestStatus::DONE, self::VET, 'Dr Claire Lambert', str_repeat('a', 281), $now);
            self::fail('A long message should be refused.');
        } catch (InvalidPortalRequestException $e) {
            self::assertSame('message', $e->propertyPath);
        }

        $this->expectException(\InvalidArgumentException::class);
        RequestResolution::of(RequestStatus::CLOSED, self::VET, 'Dr Claire Lambert', null, $now);
    }

    public function testEachKindNamesItselfInTheOwnersSentence(): void
    {
        self::assertSame(['modification', 'renouvellement d\'ordonnance'], [PortalRequestKind::PET_CHANGE->label(), PortalRequestKind::PRESCRIPTION_RENEWAL->label()]);
    }
}
