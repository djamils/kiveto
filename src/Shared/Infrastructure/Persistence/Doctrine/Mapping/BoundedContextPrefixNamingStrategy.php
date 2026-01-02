<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence\Doctrine\Mapping;

use Doctrine\ORM\Mapping\NamingStrategy;
use Symfony\Component\String\Inflector\EnglishInflector;

readonly class BoundedContextPrefixNamingStrategy implements NamingStrategy
{
    private EnglishInflector $inflector;

    public function __construct(private NamingStrategy $inner)
    {
        $this->inflector = new EnglishInflector();
    }

    public function classToTableName(string $className): string
    {
        $prefix = $this->prefixFor($className);
        $table  = $this->inner->classToTableName($className);
        $table  = $this->normalizeTableName($table, $prefix);
        $plural = $this->pluralize($table);

        return '' === $prefix ? $plural : $prefix . '__' . $plural;
    }

    /**
     * @param class-string $className
     */
    public function propertyToColumnName(string $propertyName, string $className): string
    {
        return $this->inner->propertyToColumnName($propertyName, $className);
    }

    public function embeddedFieldToColumnName(
        string $propertyName,
        string $embeddedColumnName,
        string $className,
        string $embeddedClassName,
    ): string {
        return $this->inner->embeddedFieldToColumnName(
            $propertyName,
            $embeddedColumnName,
            $className,
            $embeddedClassName,
        );
    }

    public function referenceColumnName(): string
    {
        return $this->inner->referenceColumnName();
    }

    /**
     * @param class-string $className
     */
    public function joinColumnName(string $propertyName, string $className): string
    {
        return $this->inner->joinColumnName($propertyName, $className);
    }

    public function joinTableName(
        string $sourceEntity,
        string $targetEntity,
        string $propertyName,
    ): string {
        $prefix = $this->prefixFor($sourceEntity);
        $table  = $this->inner->joinTableName($sourceEntity, $targetEntity, $propertyName);

        return '' === $prefix ? $table : $prefix . '__' . $table;
    }

    public function joinKeyColumnName(string $entityName, ?string $referencedColumnName = null): string
    {
        return $this->inner->joinKeyColumnName($entityName, $referencedColumnName);
    }

    private function prefixFor(string $className): string
    {
        $pattern = '/^App\\\\([^\\\\]+)\\\\Infrastructure\\\\Persistence\\\\Doctrine\\\\Entity\\\\/';

        if (1 !== preg_match($pattern, $className, $matches)) {
            return '';
        }

        return $this->toSnakeCase($matches[1]);
    }

    private function toSnakeCase(string $input): string
    {
        $snake = preg_replace('/(?<!^)[A-Z]/', '_$0', $input) ?? $input;

        return mb_strtolower($snake);
    }

    private function pluralize(string $table): string
    {
        $plural = $this->inflector->pluralize($table);

        return $plural[0] ?? $table;
    }

    private function normalizeTableName(string $table, string $prefix): string
    {
        $normalized = $table;

        if ('' !== $prefix && str_starts_with($normalized, $prefix . '_')) {
            $normalized = mb_substr($normalized, mb_strlen($prefix) + 1);
        }

        if (str_ends_with($normalized, '_entity')) {
            $normalized = mb_substr($normalized, 0, -7);
        }

        return $normalized;
    }
}
