<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Application\Command\Requests;

use App\Context\ClientPortal\Application\Command\SubmitPetChangeRequest\SubmitPetChangeRequest;
use App\Context\ClientPortal\Application\Port\RecordedWeight;
use App\Context\ClientPortal\Domain\Exception\InvalidPortalRequestException;
use App\Context\ClientPortal\Domain\Exception\PetNotEligibleException;
use App\Context\ClientPortal\Domain\Exception\PetNotFoundException;
use App\Context\ClientPortal\Domain\Exception\PortalRequestRateLimitedException;
use App\Context\ClientPortal\Domain\Request\ChangeField;
use App\Tests\Unit\Context\ClientPortal\Support\PetWorld as P;
use App\Tests\Unit\Context\ClientPortal\Support\Requests;
use App\Tests\Unit\Context\ClientPortal\Support\RequestWorld as W;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** AC 67, 233, 235, 236: a pet change request, its snapshot and its bell. */
final class SubmitPetChangeRequestHandlerTest extends TestCase
{
    public function testAWeightIsNormalisedSnapshottedAndAnnouncedToTheAnimalManagers(): void
    {
        $world = new W();
        $world->contact();
        $world->pets->fakes->recordedWeights[P::PET] = new RecordedWeight(11800, new \DateTimeImmutable('2026-09-01'));

        $id = $world->submitChange()(new SubmitPetChangeRequest(P::USER, P::CLINIC, P::PET, 'weight', '12,4'));

        $request = $world->changes->findById(P::CLINIC, $id);
        self::assertNotNull($request);
        self::assertSame([P::CLIENT, P::USER, P::PET, ChangeField::WEIGHT, ['weightGrams' => 12400, 'measuredOn' => '2026-10-29'], '11800', '2026-10-29 09:00:00'], [
            $request->clientId(), $request->userId(), $request->petId(), $request->change()->field(), $request->change()->normalised(),
            $request->currentValueAtSubmit(), $request->submittedAt()->format('Y-m-d H:i:s'),
        ]);
        // Fixed labels, pet and client names only: never the owner's text
        self::assertSame([[
            'clinicId'  => P::CLINIC,
            'userIds'   => [W::ASV, W::VET],
            'requestId' => $id,
            'title'     => 'Demande de modification — Moka',
            'detail'    => 'Sophie Martin · Poids',
        ]], $world->fakes->bells);
    }

    /**
     * @return iterable<string, array{string, string, ?string, ?string}>
     */
    public static function provideEachFieldSnapshotsThePetsValueCases(): iterable
    {
        yield 'name' => ['name', 'Rexy', null, 'Rex'];
        yield 'weight without record' => ['weight', '4', null, null];
        yield 'sterilization' => ['sterilization', 'intact', null, 'neutered'];
        yield 'chip' => ['chip_number', '250269604999999', null, '250269604123456'];
        yield 'other' => ['other', '', 'Nouvelle adresse', null];
    }

    #[DataProvider('provideEachFieldSnapshotsThePetsValueCases')]
    public function testEachFieldSnapshotsThePetsValue(string $field, string $value, ?string $note, ?string $current): void
    {
        $world = new W();

        $id = $world->submitChange()(new SubmitPetChangeRequest(P::USER, P::CLINIC, P::PET2, $field, $value, note: $note));

        self::assertSame($current, $world->changes->findById(P::CLINIC, $id)?->currentValueAtSubmit());
        self::assertStringEndsWith(' — Rex', $world->fakes->bells[0]['title']);
    }

    public function testWithoutKnownClientTheBellCarriesTheFieldOnly(): void
    {
        $world = new W();

        $world->submitChange()(new SubmitPetChangeRequest(P::USER, P::CLINIC, P::PET, 'name', 'Mokka'));

        self::assertSame('Nom', $world->fakes->bells[0]['detail']);
    }

    public function testAnotherClientsPetIsNotFound(): void
    {
        $this->expectException(PetNotFoundException::class);
        new W()->submitChange()(new SubmitPetChangeRequest(P::USER, P::CLINIC, P::PET3, 'name', 'Filou II'));
    }

    public function testAnArchivedPetOfAnotherClientIsNotFound(): void
    {
        $world = new W();
        $world->petState('01960000-0000-7000-8000-0000000a0009', archived: true, owner: P::CLIENT2);

        $this->expectException(PetNotFoundException::class);
        $world->submitChange()(new SubmitPetChangeRequest(P::USER, P::CLINIC, '01960000-0000-7000-8000-0000000a0009', 'name', 'Filou II'));
    }

    public function testADeceasedOrArchivedPetOfTheAccountIsNotEligible(): void
    {
        $world = new W();
        $world->pets->pet('01960000-0000-7000-8000-0000000a0008', 'Caramel', 'deceased');
        $world->petState('01960000-0000-7000-8000-0000000a0009', archived: true);

        foreach (['01960000-0000-7000-8000-0000000a0008' => 'deceased', '01960000-0000-7000-8000-0000000a0009' => 'archived'] as $petId => $state) {
            try {
                $world->submitChange()(new SubmitPetChangeRequest(P::USER, P::CLINIC, $petId, 'name', 'Bidule'));
                self::fail('The pet should not be eligible.');
            } catch (PetNotEligibleException $e) {
                self::assertSame([$petId, $state], [$e->petId, $e->state]);
            }
        }
    }

    public function testAnUnknownFieldOrAnInvalidValueIsRefused(): void
    {
        $world = new W();

        foreach ([['colour', 'roux', 'field'], ['chip_number', '123', 'proposedValue']] as [$field, $value, $property]) {
            try {
                $world->submitChange()(new SubmitPetChangeRequest(P::USER, P::CLINIC, P::PET, $field, $value));
                self::fail('The request should be refused.');
            } catch (InvalidPortalRequestException $e) {
                self::assertSame($property, $e->propertyPath);
            }
        }

        self::assertSame([], $world->changes->requests);
    }

    public function testAFourthOpenRequestOnTheSamePetAndFieldIsRateLimited(): void
    {
        $world = new W();

        foreach (['01960000-0000-7000-8000-0000000e0001', '01960000-0000-7000-8000-0000000e0002', '01960000-0000-7000-8000-0000000e0003'] as $id) {
            $world->changes->save(Requests::change($id));
        }

        // Another field of the same pet is still accepted
        $world->submitChange()(new SubmitPetChangeRequest(P::USER, P::CLINIC, P::PET, 'name', 'Mokka'));

        try {
            $world->submitChange()(new SubmitPetChangeRequest(P::USER, P::CLINIC, P::PET, 'weight', '5'));
            self::fail('The fourth open weight request should be refused.');
        } catch (PortalRequestRateLimitedException $e) {
            self::assertNull($e->retryAfterSeconds);
        }
    }
}
