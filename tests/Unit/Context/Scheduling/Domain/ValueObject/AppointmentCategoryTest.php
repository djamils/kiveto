<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Domain\ValueObject;

use App\Context\Scheduling\Domain\ValueObject\AppointmentCategory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AppointmentCategoryTest extends TestCase
{
    /**
     * @return iterable<string, array{?string, AppointmentCategory}>
     */
    public static function provideCategoryIsReadFromTheReasonCases(): iterable
    {
        yield 'null' => [null, AppointmentCategory::CONSULTATION];
        yield 'blank' => ['   ', AppointmentCategory::CONSULTATION];
        yield 'emergency' => ['Urgence — trauma', AppointmentCategory::EMERGENCY];
        yield 'road accident' => ['AVP chien', AppointmentCategory::EMERGENCY];
        yield 'poisoning' => ['Intoxication', AppointmentCategory::EMERGENCY];
        yield 'dyspnoea' => ['Dyspnée aiguë', AppointmentCategory::EMERGENCY];
        yield 'emergency wins over vaccine' => ['Urgence après rappel', AppointmentCategory::EMERGENCY];
        yield 'vaccine' => ['Consultation vaccinale', AppointmentCategory::VACCINATION];
        yield 'booster' => ['Rappel annuel', AppointmentCategory::VACCINATION];
        yield 'primo-vaccination' => ['Primo chaton', AppointmentCategory::VACCINATION];
        yield 'follow-up after surgery' => ['Contrôle post-stérilisation', AppointmentCategory::FOLLOW_UP];
        yield 'follow-up' => ['Suivi diabète', AppointmentCategory::FOLLOW_UP];
        yield 'stitches removal' => ['Retrait des points', AppointmentCategory::FOLLOW_UP];
        yield 'post-op' => ['Visite post-op', AppointmentCategory::FOLLOW_UP];
        yield 'check-up' => ['Bilan gériatrique', AppointmentCategory::CHECK_UP];
        yield 'surgery' => ['Chirurgie — détartrage', AppointmentCategory::SURGERY];
        yield 'spaying' => ['Stérilisation', AppointmentCategory::SURGERY];
        yield 'castration' => ['Castration', AppointmentCategory::SURGERY];
        yield 'excision' => ['Exérèse nodule', AppointmentCategory::SURGERY];
        yield 'osteosynthesis' => ['Ostéosynthèse', AppointmentCategory::SURGERY];
        yield 'anything else' => ['Consultation dermatologie', AppointmentCategory::CONSULTATION];
    }

    #[DataProvider('provideCategoryIsReadFromTheReasonCases')]
    public function testCategoryIsReadFromTheReason(?string $reason, AppointmentCategory $category): void
    {
        self::assertSame($category, AppointmentCategory::fromReason($reason));
    }

    public function testValuesAreTheAgendaChips(): void
    {
        self::assertSame(
            ['consult', 'urg', 'vaccin', 'suivi', 'bilan', 'chir'],
            array_map(static fn (AppointmentCategory $c): string => $c->value, AppointmentCategory::cases()),
        );
    }
}
