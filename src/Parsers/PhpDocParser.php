<?php

declare(strict_types=1);

namespace DiePHP\LaravelResourcesTypescript\Parsers;

use DiePHP\LaravelResourcesTypescript\Dtos\TypeDefinition;
use Illuminate\Http\Resources\Json\JsonResource;
use PhpParser\Node;
use ReflectionMethod;

class PhpDocParser extends AbstractParser
{
    private TypeDefinition $definition;

    private bool $hasSerializer = false;

    /** @var list<string>|null */
    private ?array $typeReferences = null;

    public function enterNode(Node $node): null|int|Node|array
    {
        parent::enterNode($node);

        if (
            $node instanceof Node\Stmt\ClassMethod
            && $node->name->toString() === 'toArray'
        ) {
            $this->hasSerializer = true;

            if (($doc = $node->getDocComment()) !== null) {
                $this->parseReturnType($doc->getText());
            }
        }

        if (
            $node instanceof Node\Stmt\Class_
            && $node->getDocComment() !== null
            && !$this->isJsonResourceClass()
        ) {
            $this->parsePropertyAnnotations($node->getDocComment()->getText());
        }

        return null;
    }

    protected function resetDefinitionState(): void
    {
        $this->definition = TypeDefinition::interface('AnonymousType');
        $this->hasSerializer = false;
        $this->typeReferences = null;
    }

    protected function getDefinition(string $name): TypeDefinition
    {
        $definition = TypeDefinition::interface($name)->merge($this->definition);

        if (!$definition->isEmpty() || $this->hasSerializer || $this->classFqcn === null
            || !is_subclass_of($this->classFqcn, JsonResource::class)) {
            return $definition;
        }

        $method = new ReflectionMethod($this->classFqcn, 'toArray');
        $declaringClass = $method->getDeclaringClass()->getName();
        $file = $method->getFileName();

        if ($declaringClass === $this->classFqcn || str_starts_with($declaringClass, 'Illuminate\\Http\\Resources\\')
            || $method->getDocComment() === false || $file === false) {
            return $definition;
        }

        $source = file_get_contents($file);

        return $source === false ? $definition : (new self())->process($source, $name);
    }

    private function parseReturnType(string $docComment): void
    {
        $docComment = preg_replace('/^\h*\*\h?/m', '', $docComment) ?? $docComment;
        $shape = $this->extractReturnArrayShape($docComment);

        if ($shape === null) {
            if (preg_match('/@return\s+((?:array|list)\s*(?<generic><(?:[^<>]+|(?&generic))*>))/i', $docComment, $matches)) {
                $this->definition->setAliasTypes($this->resolveDocumentedTypes($matches[1]));
            }

            return;
        }

        if (count($this->splitTopLevel($shape, ':')) === 1) {
            $this->definition->setAliasTypes($this->resolveDocumentedTypes('array{' . $shape . '}'));

            return;
        }

        foreach ($this->splitTopLevel($shape, ',') as $definition) {
            $definition = trim($definition);

            if ($definition === '' || !str_contains($definition, ':')) {
                continue;
            }

            [$field, $type] = array_map('trim', explode(':', $definition, 2));
            $field = ltrim($field, "* \t\n\r\0\x0B");
            $optional = str_ends_with($field, '?');
            $fieldName = rtrim($field, '?');
            $this->definition->addField($fieldName, $this->resolveDocumentedTypes($type), $optional);
        }
    }

    private function extractReturnArrayShape(string $docComment): ?string
    {
        if (!preg_match('/@return\s+array\s*{/i', $docComment, $matches, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $start = ($matches[0][1] ?? 0) + strlen($matches[0][0]) - 1;
        $depth = 0;
        $buffer = '';
        $length = strlen($docComment);

        for ($index = $start; $index < $length; $index++) {
            $character = $docComment[$index];

            if ($character === '{') {
                if ($depth > 0) {
                    $buffer .= $character;
                }

                $depth++;

                continue;
            }

            if ($character === '}') {
                $depth--;

                if ($depth === 0) {
                    return $buffer;
                }

                if ($depth > 0) {
                    $buffer .= $character;
                }

                continue;
            }

            if ($depth > 0) {
                $buffer .= $character;
            }
        }

        return null;
    }

    private function parsePropertyAnnotations(string $docComment): void
    {
        if (preg_match_all(
            '/@property(?:-read|-write)?\s+([^\s]+)\s+\$([\w]+)/i',
            $docComment,
            $matches,
            PREG_SET_ORDER,
        ) === 0) {
            return;
        }

        foreach ($matches as $match) {
            $this->definition->addField($match[2], $this->resolveDocumentedTypes($match[1]));
        }
    }

    protected function normalizeTypeToken(string $type): string
    {
        $type = parent::normalizeTypeToken($type);

        if ($this->typeReferences !== null) {
            $this->typeReferences = [...$this->typeReferences, ...$this->extractClassReferences([$type])];
        }

        return $type;
    }

    /** @return list<string> */
    private function resolveDocumentedTypes(string $type): array
    {
        $this->typeReferences = [];
        $types = $this->resolveTypeString($type);
        $this->definition->addReferences($this->typeReferences);
        $this->typeReferences = null;

        return $types;
    }
}
