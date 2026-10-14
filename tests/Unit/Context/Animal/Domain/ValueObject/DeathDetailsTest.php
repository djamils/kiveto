<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Animal\Domain\ValueObject;

use App\Context\Animal\Domain\Exception\InvalidLifeStatusException;
use App\Context\Animal\Domain\ValueObject\BodyDisposal;
use App\Context\Animal\Domain\ValueObject\DeathCause;
use App\Context\Animal\Domain\ValueObject\LifeCycle;
use App\Context\Animal\Domain\ValueObject\LifeStatus;
use PHPUnit\Framework\TestCase;

final class DeathDetailsTest extends TestCase
{
    public function testADeathCarriesItsCircumstancesAndTheBodysDisposal(): void
    {
        $lifeCycle = new LifeCycle(LifeStatus::DECEASED, new \DateTimeImmutable('2026-04-18'), null, DeathCause::EUTHANASIA, BodyDisposal::INDIVIDUAL_CREMATION);

        $lifeCycle->ensureConsistency();

        self::assertSame(DeathCause::EUTHANASIA, $lifeCycle->deathCause);
        self::assertSame(BodyDisposal::INDIVIDUAL_CREMATION, $lifeCycle->bodyDisposal);
    }

    public function testTheDetailsAreOptional(): void
    {
        $lifeCycle = new LifeCycle(LifeStatus::DECEASED, new \DateTimeImmutable('2026-04-18'), null);

        $lifeCycle->ensureConsistency();

        self::assertNull($lifeCycle->deathCause);
        self::assertNull($lifeCycle->bodyDisposal);
    }

    public function testALivingAnimalHasNoDeathDetails(): void
    {
        $this->expectException(InvalidLifeStatusException::class);

        (new LifeCycle(LifeStatus::ALIVE, null, null, DeathCause::NATURAL))->ensureConsistency();
    }

    public function testAMissingAnimalHasNoDeathDetails(): void
    {
        $this->expectException(InvalidLifeStatusException::class);

        (new LifeCycle(LifeStatus::MISSING, null, new \DateTimeImmutable('2026-04-18'), null, BodyDisposal::RETURNED_TO_OWNER))->ensureConsistency();
    }

    public function testLabels(): void
    {
        self::assertSame(
            ['Euthanasie', 'Mort naturelle', 'Accident', 'Circonstances inconnues'],
            array_map(static fn (DeathCause $c): string => $c->label(), DeathCause::cases()),
        );
        self::assertSame(
            ['Crémation individuelle', 'Crémation collective', 'Restitué au propriétaire'],
            array_map(static fn (BodyDisposal $d): string => $d->label(), BodyDisposal::cases()),
        );
    }
}
