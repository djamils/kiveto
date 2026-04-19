<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Animal\Application\Query\SearchAnimals;

use App\Context\Animal\Application\Query\SearchAnimals\AnimalListItemView;
use PHPUnit\Framework\TestCase;

final class AnimalListItemViewTest extends TestCase
{
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2026-04-19');
    }

    public function testAgeLabelNullWhenNoBirthDate(): void
    {
        self::assertNull($this->makeView(null)->ageLabel($this->now));
    }

    public function testAgeLabelLessThanOneDay(): void
    {
        $view = $this->makeView($this->now->format('Y-m-d'));

        self::assertSame('< 1 j', $view->ageLabel($this->now));
    }

    public function testAgeLabelInDays(): void
    {
        $birthDate = $this->now->modify('-10 days')->format('Y-m-d');

        self::assertSame('10 j', $this->makeView($birthDate)->ageLabel($this->now));
    }

    public function testAgeLabelInMonths(): void
    {
        $birthDate = $this->now->modify('-8 months')->format('Y-m-d');

        self::assertSame('8 mois', $this->makeView($birthDate)->ageLabel($this->now));
    }

    public function testAgeLabelExactly1YearIsMonths(): void
    {
        $birthDate = $this->now->modify('-1 year')->format('Y-m-d');

        self::assertSame('12 mois', $this->makeView($birthDate)->ageLabel($this->now));
    }

    public function testAgeLabelExactly2YearsIsAns(): void
    {
        $birthDate = $this->now->modify('-2 years')->format('Y-m-d');

        self::assertSame('2 ans', $this->makeView($birthDate)->ageLabel($this->now));
    }

    public function testAgeLabelInYears(): void
    {
        $birthDate = $this->now->modify('-3 years')->format('Y-m-d');

        self::assertSame('3 ans', $this->makeView($birthDate)->ageLabel($this->now));
    }

    private function makeView(?string $birthDate): AnimalListItemView
    {
        return new AnimalListItemView(
            id: '01234567-89ab-cdef-0123-456789abcdef',
            name: 'Rex',
            species: 'dog',
            sex: 'male',
            breedName: null,
            birthDate: $birthDate,
            color: null,
            microchipNumber: null,
            status: 'active',
            lifeStatus: 'alive',
            primaryOwnerClientId: null,
            createdAt: '2024-01-01T10:00:00+00:00',
        );
    }
}
