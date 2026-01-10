<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Persistence\Doctrine\Mapping;

use App\Billing\Infrastructure\Persistence\Doctrine\Entity\Invoice;
use App\Billing\Infrastructure\Persistence\Doctrine\Entity\Line;
use App\Foo\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use App\Shared\Infrastructure\Persistence\Doctrine\Mapping\BoundedContextPrefixNamingStrategy;
use App\Shared\Infrastructure\Something\Foo;
use Doctrine\ORM\Mapping\NamingStrategy;
use PHPUnit\Framework\TestCase;

final class BoundedContextPrefixNamingStrategyTest extends TestCase
{
    public function testClassToTableNameAddsPrefixAndPluralizes(): void
    {
        $fqcn = Invoice::class;

        $inner = $this->createMock(NamingStrategy::class);
        $inner->expects(self::once())
            ->method('classToTableName')
            ->with($fqcn)
            ->willReturn('invoice')
        ;

        $strategy = new BoundedContextPrefixNamingStrategy($inner);

        self::assertSame('billing__invoices', $strategy->classToTableName(
            $fqcn,
        ));
    }

    public function testNormalizeRemovesPrefixAndEntitySuffix(): void
    {
        $fqcn = 'App\\Translation\\Infrastructure\\Persistence\\Doctrine\\Entity\\TranslationEntryEntity';

        $inner = $this->createMock(NamingStrategy::class);
        $inner->expects(self::once())
            ->method('classToTableName')
            ->with($fqcn)
            ->willReturn('translation_entry_entity')
        ;

        $strategy = new BoundedContextPrefixNamingStrategy($inner);

        // translation prefix stripped, _entity suffix stripped, then pluralized
        self::assertSame('translation__translation_entries', $strategy->classToTableName($fqcn));
    }

    public function testNormalizeOnlySuffix(): void
    {
        $fqcn = UserEntity::class;

        $inner = $this->createMock(NamingStrategy::class);
        $inner
            ->expects(self::once())
            ->method('classToTableName')
            ->with($fqcn)
            ->willReturn('user_entity')
        ;

        $strategy = new BoundedContextPrefixNamingStrategy($inner);

        // Prefix "foo" detected and "_entity" suffix removed before pluralization
        self::assertSame('foo__users', $strategy->classToTableName($fqcn));
    }

    public function testClassToTableNameWithoutPrefixKeepsPlural(): void
    {
        $fqcn = Foo::class;

        $inner = $this->createMock(NamingStrategy::class);
        $inner->expects(self::once())
            ->method('classToTableName')
            ->with($fqcn)
            ->willReturn('foo')
        ;

        $strategy = new BoundedContextPrefixNamingStrategy($inner);

        self::assertSame('foos', $strategy->classToTableName(
            $fqcn,
        ));
    }

    public function testJoinTableNameAddsPrefix(): void
    {
        $source = Invoice::class;
        $target = Line::class;

        $inner = $this->createMock(NamingStrategy::class);
        $inner->expects(self::once())
            ->method('joinTableName')
            ->with(
                $source,
                $target,
                'lines',
            )
            ->willReturn('invoice_line')
        ;

        $strategy = new BoundedContextPrefixNamingStrategy($inner);

        self::assertSame(
            'billing__invoice_line',
            $strategy->joinTableName(
                $source,
                $target,
                'lines',
            ),
        );
    }

    public function testPassThroughMethodsDelegateToInner(): void
    {
        $inner = $this->createMock(NamingStrategy::class);

        $inner->expects(self::once())
            ->method('propertyToColumnName')
            ->with('prop', self::class)
            ->willReturn('prop_col')
        ;

        $inner->expects(self::once())
            ->method('embeddedFieldToColumnName')
            ->with('prop', 'emb', self::class, self::class)
            ->willReturn('embedded_col')
        ;

        $inner->expects(self::once())
            ->method('referenceColumnName')
            ->willReturn('id')
        ;

        $inner->expects(self::once())
            ->method('joinColumnName')
            ->with('prop', self::class)
            ->willReturn('join_col')
        ;

        $inner->expects(self::once())
            ->method('joinKeyColumnName')
            ->with(self::class, null)
            ->willReturn('entity_id')
        ;

        $strategy = new BoundedContextPrefixNamingStrategy($inner);

        self::assertSame('prop_col', $strategy->propertyToColumnName('prop', self::class));
        self::assertSame(
            'embedded_col',
            $strategy->embeddedFieldToColumnName('prop', 'emb', self::class, self::class),
        );
        self::assertSame('id', $strategy->referenceColumnName());
        self::assertSame('join_col', $strategy->joinColumnName('prop', self::class));
        self::assertSame('entity_id', $strategy->joinKeyColumnName(self::class));
    }
}
