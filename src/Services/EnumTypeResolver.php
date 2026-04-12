<?php

declare(strict_types=1);

namespace DiePHP\LaravelResourcesTypescript\Services;

use DiePHP\LaravelResourcesTypescript\Dtos\TypeDefinition;
use ReflectionClass;
use ReflectionEnum;
use ReflectionEnumBackedCase;
use Throwable;

final class EnumTypeResolver
{
    /**
     * @param array<string, TypeDefinition> $definitions
     * @return array<string, TypeDefinition>
     */
    public function resolve(array $definitions): array
    {
        $resolvedDefinitions = $definitions;
        $enumClasses = [];

        foreach ($definitions as $definition) {
            $enumClasses = [...$enumClasses, ...$this->extractEnumClasses($definition)];
        }

        foreach (array_values(array_unique($enumClasses)) as $enumClass) {
            $name = $this->shortName($enumClass);

            if (isset($resolvedDefinitions[$name]) && !$resolvedDefinitions[$name]->isEmpty()) {
                continue;
            }

            $enumDefinition = $this->buildDefinition($enumClass);

            if ($enumDefinition !== null) {
                $resolvedDefinitions[$name] = $enumDefinition;
            }
        }

        ksort($resolvedDefinitions);

        return $resolvedDefinitions;
    }

    /**
     * @return list<string>
     */
    private function extractEnumClasses(TypeDefinition $definition): array
    {
        $enumClasses = [];

        foreach ($definition->getReferences() as $reference) {
            if ($this->isEnum($reference)) {
                $enumClasses[] = $reference;
            }
        }

        return array_values(array_unique($enumClasses));
    }

    private function buildDefinition(string $enumClass): ?TypeDefinition
    {
        try {
            $reflectionEnum = new ReflectionEnum($enumClass);
        } catch (Throwable) {
            return null;
        }

        $cases = [];

        foreach ($reflectionEnum->getCases() as $case) {
            if ($case instanceof ReflectionEnumBackedCase) {
                $cases[] = is_string($case->getBackingValue())
                    ? "'" . str_replace("'", "\\'", $case->getBackingValue()) . "'"
                    : (string) $case->getBackingValue();

                continue;
            }

            $cases[] = "'" . $case->getName() . "'";
        }

        return TypeDefinition::rawAlias(
            $this->shortName($enumClass),
            $cases === [] ? 'never' : implode(' | ', $cases),
        );
    }

    private function isEnum(string $className): bool
    {
        if (!class_exists($className) && !enum_exists($className)) {
            return false;
        }

        try {
            return (new ReflectionClass($className))->isEnum();
        } catch (Throwable) {
            return false;
        }
    }

    private function shortName(string $className): string
    {
        $normalized = trim($className, '\\');
        $parts = explode('\\', $normalized);

        return (string) end($parts);
    }
}
