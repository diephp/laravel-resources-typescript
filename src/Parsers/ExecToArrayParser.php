<?php

declare(strict_types=1);

namespace DiePHP\LaravelResourcesTypescript\Parsers;

use DiePHP\LaravelResourcesTypescript\Dtos\TypeDefinition;
use DiePHP\LaravelResourcesTypescript\Dtos\GodDto;
use Illuminate\Http\Request;
use PhpParser\Node;
use ReflectionClass;
use Throwable;

class ExecToArrayParser extends AbstractParser
{
    public function enterNode(Node $node): null|int|Node|array
    {
        parent::enterNode($node);

        return null;
    }

    protected function resetDefinitionState(): void
    {
    }

    protected function getDefinition(string $name): TypeDefinition
    {
        if ($this->classFqcn === null) {
            return TypeDefinition::interface($name);
        }

        if (!class_exists($this->classFqcn)) {
            $this->attemptRequireFile($this->classFqcn);
        }

        if (!class_exists($this->classFqcn)) {
            return TypeDefinition::interface($name);
        }

        try {
            $reflectionClass = new ReflectionClass($this->classFqcn);
            $constructor = $reflectionClass->getConstructor();

            $instance = $constructor !== null && $constructor->getNumberOfRequiredParameters() > 0
                ? $reflectionClass->newInstance(new GodDto())
                : $reflectionClass->newInstance();

            if (!method_exists($instance, 'toArray')) {
                return TypeDefinition::interface($name);
            }

            return $this->definitionFromRuntimeValue($instance->toArray(new Request()), $name);
        } catch (Throwable) {
            return TypeDefinition::interface($name);
        }
    }

    private function definitionFromRuntimeValue(mixed $value, string $name): TypeDefinition
    {
        if (is_array($value)) {
            $definition = TypeDefinition::interface($name);

            foreach ($value as $fieldName => $fieldValue) {
                if (!is_string($fieldName) && !is_int($fieldName)) {
                    continue;
                }

                $types = $this->inferRuntimeTypes($fieldValue);
                $definition->addField((string) $fieldName, $types);
                $definition->addReferences($this->extractClassReferences($types));
            }

            return $definition;
        }

        $types = $this->inferRuntimeTypes($value);
        $definition = TypeDefinition::alias($name, $types);
        $definition->addReferences($this->extractClassReferences($types));

        return $definition;
    }

    /**
     * @return list<string>
     */
    private function inferRuntimeTypes(mixed $value): array
    {
        if (is_int($value)) {
            return ['int'];
        }

        if (is_float($value)) {
            return ['float'];
        }

        if (is_bool($value)) {
            return ['bool'];
        }

        if (is_string($value)) {
            return ['string'];
        }

        if ($value === null) {
            return ['null'];
        }

        if (is_array($value)) {
            return ['array'];
        }

        if (is_object($value)) {
            return [get_class($value)];
        }

        return ['mixed'];
    }

    private function attemptRequireFile(string $fullClassName): void
    {
        $path = str_replace('\\', DIRECTORY_SEPARATOR, trim($fullClassName, '\\')) . '.php';

        if (file_exists($path)) {
            require_once $path;
        }
    }
}
