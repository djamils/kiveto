<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Catalog\Application\Command\Act;

use App\Context\Catalog\Application\Command\Act\ReviseActDetails\ReviseActDetails;
use App\Context\Catalog\Application\Command\Act\ReviseActDetails\ReviseActDetailsHandler;
use App\Context\Catalog\Domain\Act\Act;
use App\Context\Catalog\Domain\Act\Event\ActDetailsRevised;
use App\Context\Catalog\Domain\Act\Exception\ActNotFoundException;
use App\Context\Catalog\Domain\Act\Exception\ArchivedActCannotBeModifiedException;
use App\Context\Catalog\Domain\Act\Repository\ActRepositoryInterface;
use App\Context\Catalog\Domain\Act\ValueObject\ActCategory;
use App\Context\Catalog\Domain\Act\ValueObject\ActCode;
use App\Context\Catalog\Domain\Act\ValueObject\ActDescription;
use App\Context\Catalog\Domain\Act\ValueObject\ActDuration;
use App\Context\Catalog\Domain\Act\ValueObject\ActId;
use App\Context\Catalog\Domain\Act\ValueObject\ActName;
use App\Context\Catalog\Domain\ValueObject\ClinicId;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use App\System\Taxation\Domain\ValueObject\TaxCategoryCode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReviseActDetailsHandler::class)]
#[CoversClass(ReviseActDetails::class)]
#[CoversClass(ActDetailsRevised::class)]
#[CoversClass(Act::class)]
final class ReviseActDetailsHandlerTest extends TestCase
{
    private const string CLINIC_ID = '01960000-0000-7000-8000-000000000b01';
    private const string ACT_ID    = '01960000-0000-7000-8000-000000000b02';

    public function testAnActTakesItsRevisedDetails(): void
    {
        $act = $this->act('Examen général');
        self::assertNotSame([], $act->pullDomainEvents());

        $repository = $this->createMock(ActRepositoryInterface::class);
        $repository->method('findById')->willReturn($act);
        $repository->expects(self::once())->method('save')->with($act);

        $published = [];
        $eventBus  = $this->createMock(EventBusInterface::class);
        $eventBus->expects(self::once())->method('publish')->willReturnCallback(
            static function (array $stamps, object ...$events) use (&$published): void {
                array_push($published, ...$events);
            },
        );

        $this->handler($repository, $eventBus)(new ReviseActDetails(
            actId: self::ACT_ID,
            clinicId: self::CLINIC_ID,
            description: 'Sous anesthésie, avec contrôle',
            category: 'SURGERY',
            estimatedDurationMinutes: 45,
            requiresAnesthesia: true,
        ));

        self::assertSame('Sous anesthésie, avec contrôle', $act->description()?->toString());
        self::assertSame(ActCategory::SURGERY, $act->category());
        self::assertSame(45, $act->estimatedDuration()->toMinutes());
        self::assertTrue($act->requiresAnesthesia());

        self::assertCount(1, $published);
        $event = $published[0];
        self::assertInstanceOf(ActDetailsRevised::class, $event);
        self::assertSame(self::ACT_ID, $event->aggregateId());
        self::assertSame([
            'actId'                    => self::ACT_ID,
            'clinicId'                 => self::CLINIC_ID,
            'description'              => 'Sous anesthésie, avec contrôle',
            'category'                 => 'SURGERY',
            'estimatedDurationMinutes' => 45,
            'requiresAnesthesia'       => true,
        ], $event->payload());
    }

    public function testClearingTheDescriptionIsARevision(): void
    {
        $act = $this->act('Examen général');
        self::assertNotSame([], $act->pullDomainEvents());

        $repository = $this->createStub(ActRepositoryInterface::class);
        $repository->method('findById')->willReturn($act);

        $eventBus = $this->createMock(EventBusInterface::class);
        $eventBus->expects(self::once())->method('publish');

        $this->handler($repository, $eventBus)(new ReviseActDetails(
            actId: self::ACT_ID,
            clinicId: self::CLINIC_ID,
            description: '  ',
            category: 'CONSULTATION',
            estimatedDurationMinutes: 20,
            requiresAnesthesia: false,
        ));

        self::assertNull($act->description());
    }

    public function testTheSameDetailsRecordNothing(): void
    {
        foreach ([null, 'Examen général'] as $description) {
            $act = $this->act($description);
            self::assertNotSame([], $act->pullDomainEvents());

            $repository = $this->createStub(ActRepositoryInterface::class);
            $repository->method('findById')->willReturn($act);

            $eventBus = $this->createMock(EventBusInterface::class);
            $eventBus->expects(self::never())->method('publish');

            $this->handler($repository, $eventBus)(new ReviseActDetails(
                actId: self::ACT_ID,
                clinicId: self::CLINIC_ID,
                description: $description,
                category: 'CONSULTATION',
                estimatedDurationMinutes: 20,
                requiresAnesthesia: false,
            ));
        }
    }

    public function testADescriptionGivenToAnActWithoutOneIsARevision(): void
    {
        $act = $this->act(null);
        self::assertNotSame([], $act->pullDomainEvents());

        $act->reviseDetails(
            ActDescription::fromString('Nouveau'),
            ActCategory::CONSULTATION,
            ActDuration::ofMinutes(20),
            false,
            new \DateTimeImmutable('2026-08-22 10:00:00'),
        );

        self::assertCount(1, $act->pullDomainEvents());
    }

    public function testAnArchivedActCannotBeRevised(): void
    {
        $act = $this->act(null);
        $act->archive(new \DateTimeImmutable('2026-08-01'));

        $repository = $this->createMock(ActRepositoryInterface::class);
        $repository->method('findById')->willReturn($act);
        $repository->expects(self::never())->method('save');

        $this->expectException(ArchivedActCannotBeModifiedException::class);

        $this->handler($repository, $this->createStub(EventBusInterface::class))(new ReviseActDetails(
            actId: self::ACT_ID,
            clinicId: self::CLINIC_ID,
            description: null,
            category: 'OTHER',
            estimatedDurationMinutes: 10,
            requiresAnesthesia: false,
        ));
    }

    public function testAnUnknownActIsRefused(): void
    {
        $repository = $this->createMock(ActRepositoryInterface::class);
        $repository->method('findById')->willReturn(null);
        $repository->expects(self::never())->method('save');

        $this->expectException(ActNotFoundException::class);

        $this->handler($repository, $this->createStub(EventBusInterface::class))(new ReviseActDetails(
            actId: self::ACT_ID,
            clinicId: self::CLINIC_ID,
            description: null,
            category: 'OTHER',
            estimatedDurationMinutes: 10,
            requiresAnesthesia: false,
        ));
    }

    private function handler(ActRepositoryInterface $repository, EventBusInterface $eventBus): ReviseActDetailsHandler
    {
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-08-22 10:00:00'));

        return new ReviseActDetailsHandler($repository, $clock, new DomainEventPublisher($eventBus));
    }

    private function act(?string $description): Act
    {
        return Act::create(
            id: ActId::fromString(self::ACT_ID),
            clinicId: ClinicId::fromString(self::CLINIC_ID),
            name: ActName::fromString('Consultation standard'),
            code: ActCode::fromString('CONS_STD'),
            description: ActDescription::fromNullableString($description),
            category: ActCategory::CONSULTATION,
            taxCategory: TaxCategoryCode::fromString('veterinary.act.consultation'),
            basePrice: Money::fromMinorUnits(4167, CurrencyCode::fromString('EUR')),
            estimatedDuration: ActDuration::ofMinutes(20),
            requiresAnesthesia: false,
            createdAt: new \DateTimeImmutable('2026-01-01 10:00:00'),
        );
    }
}
