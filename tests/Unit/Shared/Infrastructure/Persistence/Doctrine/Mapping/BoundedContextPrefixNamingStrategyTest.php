<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Persistence\Doctrine\Mapping;

use App\Shared\Infrastructure\Persistence\Doctrine\Mapping\BoundedContextPrefixNamingStrategy;
use Doctrine\ORM\Mapping\NamingStrategy;
use PHPUnit\Framework\TestCase;

final class BoundedContextPrefixNamingStrategyTest extends TestCase
{
    public function testClassToTableNameAddsPrefixAndPluralizes(): void
    {
        $inner = $this->createMock(NamingStrategy::class);
        $inner->expects(self::once())
            ->method('classToTableName')
            ->with('App\\Billing\\Infrastructure\\Persistence\\Doctrine\\Entity\\Invoice')
            ->willReturn('invoice');

        $strategy = new BoundedContextPrefixNamingStrategy($inner);

        self::assertSame('billing__invoices', $strategy->classToTableName(
            'App\\Billing\\Infrastructure\\Persistence\\Doctrine\\Entity\\Invoice',
        ));
    }

    public function testClassToTableNameWithoutPrefixKeepsPlural(): void
    {
        $inner = $this->createMock(NamingStrategy::class);
        $inner->expects(self::once())
            ->method('classToTableName')
            ->with('App\\Shared\\Infrastructure\\Something\\Foo')
            ->willReturn('foo');

        $strategy = new BoundedContextPrefixNamingStrategy($inner);

        self::assertSame('foos', $strategy->classToTableName(
            'App\\Shared\\Infrastructure\\Something\\Foo',
        ));
    }

    public function testJoinTableNameAddsPrefix(): void
    {
        $inner = $this->createMock(NamingStrategy::class);
        $inner->expects(self::once())
            ->method('joinTableName')
            ->with(
                'App\\Billing\\Infrastructure\\Persistence\\Doctrine\\Entity\\Invoice',
                'App\\Billing\\Infrastructure\\Persistence\\Doctrine\\Entity\\Line',
                'lines',
            )
            ->willReturn('invoice_line');

        $strategy = new BoundedContextPrefixNamingStrategy($inner);

        self::assertSame(
            'billing__invoice_line',
            $strategy->joinTableName(
                'App\\Billing\\Infrastructure\\Persistence\\Doctrine\\Entity\\Invoice',
                'App\\Billing\\Infrastructure\\Persistence\\Doctrine\\Entity\\Line',
                'lines',
            ),
        );
    }

    public function testPassThroughMethodsDelegateToInner(): void
    {
        $inner = $this->createMock(NamingStrategy::class);

        $inner->expects(self::once())
            ->method('propertyToColumnName')
            ->with('prop', 'Cls')
            ->willReturn('prop_col');

        $inner->expects(self::once())
            ->method('embeddedFieldToColumnName')
            ->with('prop', 'emb', 'Cls', 'EmbCls')
            ->willReturn('embedded_col');

        $inner->expects(self::once())
            ->method('referenceColumnName')
            ->willReturn('id');

        $inner->expects(self::once())
            ->method('joinColumnName')
            ->with('prop', 'Cls')
            ->willReturn('join_col');

        $inner->expects(self::once())
            ->method('joinKeyColumnName')
            ->with('Entity', null)
            ->willReturn('entity_id');

        $strategy = new BoundedContextPrefixNamingStrategy($inner);

        self::assertSame('prop_col', $strategy->propertyToColumnName('prop', 'Cls'));
        self::assertSame('embedded_col', $strategy->embeddedFieldToColumnName('prop', 'emb', 'Cls', 'EmbCls'));
        self::assertSame('id', $strategy->referenceColumnName());
        self::assertSame('join_col', $strategy->joinColumnName('prop', 'Cls'));
        self::assertSame('entity_id', $strategy->joinKeyColumnName('Entity'));
    }
}

