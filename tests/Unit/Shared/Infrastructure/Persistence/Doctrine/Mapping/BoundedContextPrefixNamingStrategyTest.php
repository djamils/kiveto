<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Persistence\Doctrine\Mapping;

use App\Shared\Infrastructure\Persistence\Doctrine\Mapping\BoundedContextPrefixNamingStrategy;
use Doctrine\ORM\Mapping\NamingStrategy;
use PHPUnit\Framework\TestCase;

final class BoundedContextPrefixNamingStrategyTest extends TestCase
{
    public function testClassToTableNameAddsContextBucketPrefixAndPluralizes(): void
    {
        $fqcn = 'App\\Context\\Clinic\\Infrastructure\\Persistence\\Doctrine\\Entity\\ClinicEntity';

        $inner = $this->createMock(NamingStrategy::class);
        $inner->expects(self::once())
            ->method('classToTableName')
            ->with($fqcn)
            ->willReturn('clinic')
        ;

        $strategy = new BoundedContextPrefixNamingStrategy($inner);

        self::assertSame('clinic__clinics', $strategy->classToTableName($fqcn));
    }

    public function testNormalizeRemovesEntitySuffixForSystemBucket(): void
    {
        /** @var class-string $fqcn */
        $fqcn = 'App\\System\\Translation\\Infrastructure\\Persistence\\Doctrine\\Entity\\TranslationEntryEntity';

        $inner = $this->createMock(NamingStrategy::class);
        $inner->expects(self::once())
            ->method('classToTableName')
            ->with($fqcn)
            ->willReturn('translation_entry_entity')
        ;

        $strategy = new BoundedContextPrefixNamingStrategy($inner);

        // translation prefix derived, _entity suffix stripped, then pluralized
        self::assertSame('translation__translation_entries', $strategy->classToTableName($fqcn));
    }

    public function testCamelCaseBoundedContextNameIsSnakeCased(): void
    {
        /** @var class-string $fqcn */
        $fqcn = 'App\\System\\IdentityAccess\\Infrastructure\\Persistence\\Doctrine\\Entity\\UserEntity';

        $inner = $this->createMock(NamingStrategy::class);
        $inner
            ->expects(self::once())
            ->method('classToTableName')
            ->with($fqcn)
            ->willReturn('user_entity')
        ;

        $strategy = new BoundedContextPrefixNamingStrategy($inner);

        self::assertSame('identity_access__users', $strategy->classToTableName($fqcn));
    }

    public function testClassToTableNameWithoutBucketPrefixKeepsPlural(): void
    {
        /** @var class-string $fqcn */
        $fqcn = 'App\\Shared\\Infrastructure\\Something\\Foo';

        $inner = $this->createMock(NamingStrategy::class);
        $inner->expects(self::once())
            ->method('classToTableName')
            ->with($fqcn)
            ->willReturn('foo')
        ;

        $strategy = new BoundedContextPrefixNamingStrategy($inner);

        self::assertSame('foos', $strategy->classToTableName($fqcn));
    }

    public function testJoinTableNameAddsPrefix(): void
    {
        $source = 'App\\Context\\Clinic\\Infrastructure\\Persistence\\Doctrine\\Entity\\ClinicEntity';
        $target = 'App\\Context\\Clinic\\Infrastructure\\Persistence\\Doctrine\\Entity\\ClinicGroupEntity';

        $inner = $this->createMock(NamingStrategy::class);
        $inner->expects(self::once())
            ->method('joinTableName')
            ->with($source, $target, 'lines')
            ->willReturn('invoice_line')
        ;

        $strategy = new BoundedContextPrefixNamingStrategy($inner);

        self::assertSame(
            'clinic__invoice_line',
            $strategy->joinTableName($source, $target, 'lines'),
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
