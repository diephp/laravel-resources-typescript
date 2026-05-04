<?php

declare(strict_types=1);

namespace DiePHP\LaravelResourcesTypescript\Services;

use DiePHP\LaravelResourcesTypescript\Dtos\TypeDefinition;
use DiePHP\LaravelResourcesTypescript\Parsers\FillableParser;
use DiePHP\LaravelResourcesTypescript\Parsers\PhpDocParser;
use DiePHP\LaravelResourcesTypescript\Parsers\PublicPropertyParser;
use ReflectionClass;
use Throwable;

final class ReferencedTypeResolver
{
    /**
     * @param list<class-string> $classParsers
     */
    public function __construct(
        private ?EnumTypeResolver $enumTypeResolver = null,
        private array $classParsers = [
            PublicPropertyParser::class,
            PhpDocParser::class,
            FillableParser::class,
        ],
    ) {
        $this->enumTypeResolver ??= new EnumTypeResolver();
    }

    /**
     * @param array<string, TypeDefinition> $definitions
     * @return array<string, TypeDefinition>
     */
    public function resolve(array $definitions): array
    {
        $resolvedDefinitions = $definitions;
        $resolvedDefinitions = $this->enumTypeResolver->resolve($resolvedDefinitions);

        do {
            $hasChanges = false;

            foreach ($this->extractReferencedClasses($resolvedDefinitions) as $reference) {
                $name = $this->shortName($reference);

                if (isset($resolvedDefinitions[$name]) && !$resolvedDefinitions[$name]->isEmpty()) {
                    continue;
                }

                if ($this->isEnum($reference)) {
                    continue;
                }

                $definition = $this->buildClassDefinition($reference);

                if ($definition === null || $definition->isEmpty()) {
                    continue;
                }

                $resolvedDefinitions[$name] = $definition;
                $resolvedDefinitions = $this->enumTypeResolver->resolve($resolvedDefinitions);
                $hasChanges = true;
            }
        } while ($hasChanges);

        ksort($resolvedDefinitions);

        return $resolvedDefinitions;
    }

    /**
     * @param array<string, TypeDefinition> $definitions
     * @return list<string>
     */
    private function extractReferencedClasses(array $definitions): array
    {
        $references = [];

        foreach ($definitions as $definition) {
            foreach ($definition->getReferences() as $reference) {
                $references[] = $reference;
            }
        }

        return array_values(array_unique($references));
    }

    private function buildClassDefinition(string $className): ?TypeDefinition
    {
        if (!class_exists($className)) {
            return null;
        }

        try {
            $reflection = new ReflectionClass($className);
        } catch (Throwable) {
            return null;
        }

        $fileName = $reflection->getFileName();

        if (!is_string($fileName) || !is_file($fileName)) {
            return $this->buildDefinitionFromReflection($reflection);
        }

        $code = file_get_contents($fileName);

        if ($code === false) {
            return $this->buildDefinitionFromReflection($reflection);
        }

        $definition = TypeDefinition::interface($reflection->getShortName());

        foreach ($this->classParsers as $parserClass) {
            try {
                $parser = new $parserClass();
                $definition = $definition->merge($parser->process($code, $reflection->getShortName()));
            } catch (Throwable) {
                continue;
            }
        }

        return $definition->isEmpty()
            ? $this->buildDefinitionFromReflection($reflection)
            : $definition;
    }

    private function buildDefinitionFromReflection(ReflectionClass $reflection): TypeDefinition
    {
        $definition = TypeDefinition::interface($reflection->getShortName());

        foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            $type = $property->getType();

            if ($type === null) {
                $definition->addField($property->getName(), 'mixed', true);

                continue;
            }

            $typeName = $type instanceof \ReflectionNamedType
                ? $type->getName()
                : 'mixed';

            $types = [$typeName];

            if ($type->allowsNull() && !in_array('null', $types, true)) {
                $types[] = 'null';
            }

            $definition->addField($property->getName(), $types);
        }

        return $definition;
    }

    private function isEnum(string $className): bool
    {
        return enum_exists($className);
    }

    private function shortName(string $className): string
    {
        $normalized = trim($className, '\\');
        $parts = explode('\\', $normalized);

        return (string) end($parts);
    }
}
