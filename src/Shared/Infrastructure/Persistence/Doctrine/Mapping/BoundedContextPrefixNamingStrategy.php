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

    public function classToTableName($className): string
    {
        $prefix = $this->prefixFor($className);
        $table  = $this->inner->classToTableName($className);
        $plural = $this->pluralize($table);

        return '' === $prefix ? $plural : $prefix . '__' . $plural;
    }

    public function propertyToColumnName($propertyName, $className = null): string
    {
        return $this->inner->propertyToColumnName($propertyName, $className);
    }

    public function embeddedFieldToColumnName($propertyName, $embeddedColumnName, $className, string $embeddedClassName): string
    {
        return $this->inner->embeddedFieldToColumnName($propertyName, $embeddedColumnName, $className, $embeddedClassName);
    }

    public function referenceColumnName(): string
    {
        return $this->inner->referenceColumnName();
    }

    public function joinColumnName($propertyName, $className = null): string
    {
        return $this->inner->joinColumnName($propertyName, $className);
    }

    public function joinTableName($sourceEntity, $targetEntity, $propertyName = null): string
    {
        $prefix = $this->prefixFor($sourceEntity);
        $table  = $this->inner->joinTableName($sourceEntity, $targetEntity, $propertyName);

        return '' === $prefix ? $table : $prefix . '__' . $table;
    }

    public function joinKeyColumnName($entityName, $referencedColumnName = null): string
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
}
