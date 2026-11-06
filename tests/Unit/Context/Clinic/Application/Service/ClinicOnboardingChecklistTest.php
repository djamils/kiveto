<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Application\Service;

use App\Context\Clinic\Application\Port\InvitationStatusReaderInterface;
use App\Context\Clinic\Application\Query\Account\GetClinicOnboarding\ClinicOnboardingView;
use App\Context\Clinic\Application\Query\Account\GetClinicOnboarding\OnboardingStepView;
use App\Context\Clinic\Application\Service\ClinicAccounts;
use App\Context\Clinic\Application\Service\ClinicOnboardingChecklist;
use App\Context\Clinic\Domain\Account\ClinicAccount;
use App\Context\Clinic\Domain\ValueObject\ClinicId;
use App\Context\Clinic\Domain\ValueObject\OpeningHours;
use App\Context\Clinic\Domain\ValueObject\OpeningSlot;
use App\Tests\Unit\Context\Clinic\Support\ConsoleFakes;
use App\Tests\Unit\Context\Clinic\Support\InMemoryClinicAccounts;
use App\Tests\Unit\Context\Clinic\Support\InMemoryClinics;
use PHPUnit\Framework\TestCase;

final class ClinicOnboardingChecklistTest extends TestCase
{
    private const string CLINIC = '01960000-0000-7000-8000-00000000c2a1';
    private const string OTHER  = '01960000-0000-7000-8000-00000000c2a2';

    private InMemoryClinics $clinics;
    private InMemoryClinicAccounts $accounts;
    private ConsoleFakes $console;

    protected function setUp(): void
    {
        $this->clinics  = new InMemoryClinics();
        $this->accounts = new InMemoryClinicAccounts();
        $this->console  = new ConsoleFakes();
        $this->clinics->add(InMemoryClinics::clinic(self::CLINIC));
    }

    public function testANewClinicHasOnlyBeenCreated(): void
    {
        $view = $this->checklist()->of(self::CLINIC);

        self::assertSame([
            ['created', 'Clinique créée', true, '', true],
            ['invited', 'Invitations envoyées', false, 'Aucune personne invitée', true],
            ['accepted', 'Toutes les invitations acceptées', false, '—', true],
            ['imported', 'Données de l’ancien logiciel importées', false, 'Migration non gérée en pré-alpha', false],
            ['hours', 'Horaires d’ouverture configurés', false, 'Fait par l’admin clinique dans la VetApp', true],
            ['appointment', 'Premier rendez-vous créé', false, '—', true],
        ], self::steps($view));
        self::assertSame([1, 5, false, null], [$view->done(), $view->total(), $view->isComplete(), $view->onboardedAt]);
        self::assertSame([], $this->console->askedStatuses, 'no member, no question to IdentityAccess');
    }

    /**
     * A disabled member is not waited for; an account IdentityAccess does
     * not know (absent from its answer) does not hold the clinic either.
     */
    public function testTheMembersStillWaitingAreNamed(): void
    {
        $this->console->memberRows = [
            ConsoleFakes::memberRow('u-claire', self::CLINIC, 'Claire', 'Martin'),
            ConsoleFakes::memberRow('u-ines', self::CLINIC, 'Inès', 'Benali'),
            ConsoleFakes::memberRow('u-hugo', self::CLINIC, displayName: 'Hugo'),
            ConsoleFakes::memberRow('u-unknown', self::CLINIC, 'Paul', 'Durand'),
            ConsoleFakes::memberRow('u-gone', self::CLINIC, 'Anne', 'Petit', status: 'DISABLED'),
            ConsoleFakes::memberRow('u-elsewhere', self::OTHER, 'Marc', 'Roux'),
        ];
        $this->console->statuses = [
            'u-claire' => InvitationStatusReaderInterface::ACTIVE,
            'u-ines'   => InvitationStatusReaderInterface::INVITED,
            'u-hugo'   => InvitationStatusReaderInterface::EXPIRED,
            'u-gone'   => InvitationStatusReaderInterface::INVITED,
        ];

        $steps = self::steps($this->checklist()->of(self::CLINIC));

        self::assertSame(['invited', '4 invitations envoyées', true, 'Liens valables 7 jours', true], $steps[1]);
        self::assertSame(['accepted', 'Toutes les invitations acceptées', false, '2 sur 4 · Inès Benali et Hugo en attente', true], $steps[2]);
        self::assertSame([[self::CLINIC, ['u-claire', 'u-ines', 'u-hugo', 'u-unknown']]], $this->console->askedStatuses);
    }

    public function testThreeWaitingMembersAreListedWithCommas(): void
    {
        $this->console->memberRows = [
            ConsoleFakes::memberRow('u-ines', self::CLINIC, 'Inès', 'Benali'),
            ConsoleFakes::memberRow('u-hugo', self::CLINIC, 'Hugo', ''),
            ConsoleFakes::memberRow('u-paul', self::CLINIC, '', 'Durand'),
        ];
        $this->console->statuses = array_fill_keys(['u-ines', 'u-hugo', 'u-paul'], InvitationStatusReaderInterface::INVITED);

        self::assertSame('0 sur 3 · Inès Benali, Hugo et Durand en attente', self::steps($this->checklist()->of(self::CLINIC))[2][3]);
    }

    public function testOneInvitationIsSingular(): void
    {
        $this->console->memberRows = [ConsoleFakes::memberRow('u-ines', self::CLINIC, 'Inès', 'Benali')];
        $this->console->statuses   = ['u-ines' => InvitationStatusReaderInterface::EXPIRED];

        $steps = self::steps($this->checklist()->of(self::CLINIC));

        self::assertSame(['1 invitation envoyée', '0 sur 1 · Inès Benali en attente'], [$steps[1][1], $steps[2][3]]);
    }

    public function testAClinicWithEveryCountedStepDoneIsComplete(): void
    {
        $this->clinics->add(InMemoryClinics::clinic(self::CLINIC, openingHours: OpeningHours::create([1 => [OpeningSlot::create('08:00', '12:00')]])));
        $this->console->memberRows = [ConsoleFakes::memberRow('u-claire', self::CLINIC, 'Claire', 'Martin')];
        $this->console->statuses   = ['u-claire' => InvitationStatusReaderInterface::ACTIVE];
        $this->console->booked     = [self::CLINIC];

        $view  = $this->checklist()->of(self::CLINIC);
        $steps = self::steps($view);

        self::assertSame(['accepted', 'Toutes les invitations acceptées', true, 'Toute l’équipe s’est connectée', true], $steps[2]);
        self::assertTrue($steps[4][2], 'opening hours');
        self::assertSame(['appointment', 'Premier rendez-vous créé', true, 'L’agenda est utilisé', true], $steps[5]);
        self::assertSame([5, 5, true], [$view->done(), $view->total(), $view->isComplete()]);
    }

    public function testAnOnboardedClinicStaysCompleteWhateverItsSteps(): void
    {
        $at      = new \DateTimeImmutable('2026-10-01 08:00:00', new \DateTimeZone('UTC'));
        $account = ClinicAccount::defaultFor(ClinicId::fromString(self::CLINIC), $at);
        $account->markOnboarded($at);
        $this->accounts->save($account);

        $view = $this->checklist()->of(self::CLINIC);

        self::assertSame([1, 5, true, $at], [$view->done(), $view->total(), $view->isComplete(), $view->onboardedAt]);
    }

    public function testTheViewCountsOnlyTheCountedSteps(): void
    {
        $view = new ClinicOnboardingView([
            new OnboardingStepView('a', 'A', true, ''),
            new OnboardingStepView('b', 'B', true, '', counted: false),
            new OnboardingStepView('c', 'C', false, '', counted: false),
        ], null);

        self::assertSame([1, 1, true], [$view->done(), $view->total(), $view->isComplete()]);
    }

    /**
     * @return list<array{string, string, bool, string, bool}>
     */
    private static function steps(ClinicOnboardingView $view): array
    {
        return array_map(static fn (OnboardingStepView $step): array => [$step->key, $step->label, $step->done, $step->detail, $step->counted], $view->steps);
    }

    private function checklist(): ClinicOnboardingChecklist
    {
        return new ClinicOnboardingChecklist(new ClinicAccounts($this->clinics, $this->accounts), $this->console, $this->console, $this->console);
    }
}
