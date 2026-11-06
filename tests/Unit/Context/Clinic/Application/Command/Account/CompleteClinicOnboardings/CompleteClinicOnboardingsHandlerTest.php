<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Application\Command\Account\CompleteClinicOnboardings;

use App\Context\Clinic\Application\Command\Account\CompleteClinicOnboardings\CompleteClinicOnboardings;
use App\Context\Clinic\Application\Command\Account\CompleteClinicOnboardings\CompleteClinicOnboardingsHandler;
use App\Context\Clinic\Application\Port\InvitationStatusReaderInterface;
use App\Context\Clinic\Application\Service\ClinicAccounts;
use App\Context\Clinic\Application\Service\ClinicOnboardingChecklist;
use App\Context\Clinic\Domain\Account\ClinicAccount;
use App\Context\Clinic\Domain\ValueObject\ClinicId;
use App\Context\Clinic\Domain\ValueObject\OpeningHours;
use App\Context\Clinic\Domain\ValueObject\OpeningSlot;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Unit\Context\Clinic\Support\ConsoleFakes;
use App\Tests\Unit\Context\Clinic\Support\InMemoryClinicAccounts;
use App\Tests\Unit\Context\Clinic\Support\InMemoryClinics;
use PHPUnit\Framework\TestCase;

final class CompleteClinicOnboardingsHandlerTest extends TestCase
{
    private const string READY      = '01960000-0000-7000-8000-00000000c7a1';
    private const string SUSPENDED  = '01960000-0000-7000-8000-00000000c7a2';
    private const string ONBOARDED  = '01960000-0000-7000-8000-00000000c7a3';
    private const string ONBOARDING = '01960000-0000-7000-8000-00000000c7a4';
    private const string STAMPED    = '01960000-0000-7000-8000-00000000c7a5';

    public function testOnlyTheActiveClinicsWhoseStepsAreAllDoneBecomeActivePilots(): void
    {
        $now      = new \DateTimeImmutable('2026-11-06 09:15:00', new \DateTimeZone('UTC'));
        $hours    = OpeningHours::create([1 => [OpeningSlot::create('08:00', '12:00')]]);
        $clinics  = new InMemoryClinics();
        $accounts = new InMemoryClinicAccounts();
        $console  = new ConsoleFakes();

        foreach ([self::READY, self::SUSPENDED, self::ONBOARDED, self::ONBOARDING, self::STAMPED] as $id) {
            $clinics->add(InMemoryClinics::clinic($id, openingHours: self::ONBOARDING === $id ? null : $hours));
            $console->memberRows[]         = ConsoleFakes::memberRow('u-' . $id, $id, 'Claire', 'Martin');
            $console->statuses['u-' . $id] = InvitationStatusReaderInterface::ACTIVE;
            $console->booked[]             = $id;
        }

        // The console still lists it as onboarding, but its account was stamped meanwhile.
        $stamped = ClinicAccount::defaultFor(ClinicId::fromString(self::STAMPED), new \DateTimeImmutable('2026-09-01'));
        $stamped->markOnboarded(new \DateTimeImmutable('2026-11-06 09:00:00', new \DateTimeZone('UTC')));
        $accounts->accounts[self::STAMPED] = $stamped;

        $console->clinicRows = [
            ConsoleFakes::clinicRow(self::READY),
            ConsoleFakes::clinicRow(self::SUSPENDED, 'suspended'),
            ConsoleFakes::clinicRow(self::ONBOARDED, onboardedAt: new \DateTimeImmutable('2026-10-01')),
            ConsoleFakes::clinicRow(self::ONBOARDING),
            ConsoleFakes::clinicRow(self::STAMPED),
        ];

        $service   = new ClinicAccounts($clinics, $accounts);
        $completed = new CompleteClinicOnboardingsHandler($console, new ClinicOnboardingChecklist($service, $console, $console, $console), $service, $accounts, new FrozenClock($now))(
            new CompleteClinicOnboardings(),
        );

        self::assertSame(1, $completed);
        self::assertSame([self::READY], $accounts->saved);
        self::assertSame($now, $accounts->accounts[self::READY]->onboardedAt());
        self::assertSame('2026-11-06 09:00:00', $stamped->onboardedAt()?->format('Y-m-d H:i:s'), 'the first stamp is kept');
        self::assertSame(
            [self::READY, self::ONBOARDING, self::STAMPED],
            array_map(static fn (array $asked): string => $asked[0], $console->askedStatuses),
            'a suspended or onboarded clinic is not even looked at',
        );
    }
}
