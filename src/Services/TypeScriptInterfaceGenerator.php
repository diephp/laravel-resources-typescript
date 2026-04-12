<?php

declare(strict_types=1);

namespace DiePHP\LaravelResourcesTypescript\Services;

use DiePHP\LaravelResourcesTypescript\Dtos\FieldDefinition;
use DiePHP\LaravelResourcesTypescript\Dtos\TypeDefinition;

class TypeScriptInterfaceGenerator
{
    /**
     * @param array<string, string> $phpToTsTypeMap
     */
    public function __construct(
        private array $phpToTsTypeMap = [],
    ) {
    }

    /**
     * @param array<string, TypeDefinition|array<string, string|array<int, string>>> $resourcesData
     */
    public function generate(array $resourcesData): string
    {
        $output = [];

        foreach ($resourcesData as $name => $definition) {
            $definition = $this->normalizeDefinition($name, $definition);

            if ($definition->getKind() === TypeDefinition::KIND_ALIAS) {
                $type = $definition->getRawTypeScriptType()
                    ?? $this->renderTypes($definition->getAliasTypes());

                $output[] = sprintf('export type %s = %s;', $definition->getName(), $type);

                continue;
            }

            $lines = [sprintf('export interface %s {', $definition->getName())];

            foreach ($definition->getFields() as $fieldName => $fieldDefinition) {
                $lines[] = sprintf(
                    '  %s%s: %s;',
                    $fieldName,
                    $fieldDefinition->isOptional() ? '?' : '',
                    $this->renderField($fieldDefinition),
                );
            }

            $lines[] = '}';
            $output[] = implode(PHP_EOL, $lines);
        }

        return implode(PHP_EOL . PHP_EOL, $output) . PHP_EOL;
    }

    public function store(string $content, string $path): false|int
    {
        $directory = dirname($path);

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        return file_put_contents($path, $content);
    }

    private function renderField(FieldDefinition $definition): string
    {
        return $this->renderTypes($definition->getTypes());
    }

    /**
     * @param list<string> $types
     */
    private function renderTypes(array $types): string
    {
        $renderedTypes = array_values(array_unique(array_map(
            fn (string $type): string => $this->mapPhpTypeToTypeScript($type),
            $types,
        )));

        $specificTypes = array_values(array_filter(
            $renderedTypes,
            static fn (string $type): bool => $type !== 'any',
        ));

        return implode(' | ', $specificTypes === [] ? ['any'] : $specificTypes);
    }

    private function mapPhpTypeToTypeScript(string $type): string
    {
        if (str_ends_with($type, '[]')) {
            return $this->mapPhpTypeToTypeScript(substr($type, 0, -2)) . '[]';
        }

        $normalized = trim($type, '\\');

        if (isset($this->phpToTsTypeMap[$normalized])) {
            return $this->phpToTsTypeMap[$normalized];
        }

        return $this->shortName($normalized);
    }

    /**
     * @param TypeDefinition|array<string, string|array<int, string>> $definition
     */
    private function normalizeDefinition(string $name, TypeDefinition|array $definition): TypeDefinition
    {
        if ($definition instanceof TypeDefinition) {
            return $definition;
        }

        $normalized = TypeDefinition::interface($name);

        foreach ($definition as $fieldName => $types) {
            $normalized->addField($fieldName, $types);
        }

        return $normalized;
    }

    private function shortName(string $type): string
    {
        if (!str_contains($type, '\\')) {
            return $type;
        }

        $parts = explode('\\', $type);

        return (string) end($parts);
    }
}
