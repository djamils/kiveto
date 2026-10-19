<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Taxation\Domain;

use App\Shared\Domain\Time\ClockInterface;
use App\System\Taxation\Domain\Event\TaxCategoryRegistered;
use App\System\Taxation\Domain\TaxCategory;
use App\System\Taxation\Domain\ValueObject\TaxCategoryCode;
use App\System\Taxation\Domain\ValueObject\TaxCategoryNature;
use PHPUnit\Framework\TestCase;

final class TaxCategoryTest extends TestCase
{
    private \DateTimeImmutable $now;
    private ClockInterface $clock;

    protected function setUp(): void
    {
        $this->now   = new \DateTimeImmutable('2024-01-01T10:00:00+00:00');
        $this->clock = $this->createStub(ClockInterface::class);
        $this->clock->method('now')->willReturn($this->now);
    }

    public function testCreateRecordsTaxCategoryRegisteredEvent(): void
    {
        $category = TaxCategory::create(
            TaxCategoryCode::fromString('veterinary.act.consultation'),
            'Acte de consultation vétérinaire',
            null,
            TaxCategoryNature::SERVICES,
            $this->clock,
        );

        $events = $category->pullDomainEvents();

        self::assertCount(1, $events);
        self::assertInstanceOf(TaxCategoryRegistered::class, $events[0]);
    }

    public function testCreateSetsFieldsCorrectly(): void
    {
        $code     = TaxCategoryCode::fromString('veterinary.act.consultation');
        $category = TaxCategory::create($code, 'Acte de consultation', 'Description', TaxCategoryNature::SERVICES, $this->clock);

        self::assertSame('veterinary.act.consultation', $category->code()->toString());
        self::assertSame('Acte de consultation', $category->displayName());
        self::assertSame('Description', $category->description());
        self::assertSame(TaxCategoryNature::SERVICES, $category->nature());
        self::assertTrue($category->isActive());
        self::assertSame($this->now, $category->createdAt());
        self::assertSame($this->now, $category->updatedAt());
    }

    public function testReconstituteRestoresStateWithoutEvents(): void
    {
        $createdAt = new \DateTimeImmutable('2023-01-01');
        $updatedAt = new \DateTimeImmutable('2023-06-01');

        $category = TaxCategory::reconstitute(
            TaxCategoryCode::fromString('veterinary.act.consultation'),
            'Acte de consultation',
            null,
            TaxCategoryNature::SERVICES,
            true,
            $createdAt,
            $updatedAt,
        );

        self::assertSame([], $category->pullDomainEvents());
        self::assertTrue($category->isActive());
        self::assertSame($createdAt, $category->createdAt());
        self::assertSame($updatedAt, $category->updatedAt());
    }

    public function testActivateIsIdempotent(): void
    {
        $category = TaxCategory::create(
            TaxCategoryCode::fromString('veterinary.act.consultation'),
            'Test',
            null,
            TaxCategoryNature::SERVICES,
            $this->clock,
        );

        $category->deactivate();
        // clear events from create
        $_ = $category->pullDomainEvents();

        $category->activate();
        $category->activate();

        self::assertTrue($category->isActive());
    }

    public function testDeactivateIsIdempotent(): void
    {
        $category = TaxCategory::create(
            TaxCategoryCode::fromString('veterinary.act.consultation'),
            'Test',
            null,
            TaxCategoryNature::SERVICES,
            $this->clock,
        );

        $category->deactivate();
        $category->deactivate();

        self::assertFalse($category->isActive());
    }

    public function testUpdateDisplayUpdatesFields(): void
    {
        $laterTime  = new \DateTimeImmutable('2024-06-01T10:00:00+00:00');
        $laterClock = $this->createStub(ClockInterface::class);
        $laterClock->method('now')->willReturn($laterTime);

        $category = TaxCategory::create(
            TaxCategoryCode::fromString('veterinary.act.consultation'),
            'Old name',
            'Old description',
            TaxCategoryNature::SERVICES,
            $this->clock,
        );

        $category->update('New name', 'New description', TaxCategoryNature::GOODS, $laterClock);

        self::assertSame('New name', $category->displayName());
        self::assertSame('New description', $category->description());
        self::assertSame(TaxCategoryNature::GOODS, $category->nature());
        self::assertSame($laterTime, $category->updatedAt());
    }
}
