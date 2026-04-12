<?php

declare(strict_types=1);

namespace DiePHP\LaravelResourcesTypescript\Parsers;

use DiePHP\LaravelResourcesTypescript\Dtos\TypeDefinition;
use PhpParser\Node;

class PhpDocParser extends AbstractParser
{
    private TypeDefinition $definition;

    public function enterNode(Node $node): null|int|Node|array
    {
        parent::enterNode($node);

        if (
            $node instanceof Node\Stmt\ClassMethod
            && $node->name->toString() === 'toArray'
            && $node->getDocComment() !== null
        ) {
            $this->parseReturnArrayShape($node->getDocComment()->getText());
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
    }

    protected function getDefinition(string $name): TypeDefinition
    {
        return TypeDefinition::interface($name)->merge($this->definition);
    }

    private function parseReturnArrayShape(string $docComment): void
    {
        $shape = $this->extractReturnArrayShape($docComment);

        if ($shape === null) {
            return;
        }

        $shape = preg_replace('/^\s*\*\s?/m', '', $shape) ?? $shape;

        foreach ($this->splitTopLevel($shape, ',') as $definition) {
            $definition = trim($definition);

            if ($definition === '' || !str_contains($definition, ':')) {
                continue;
            }

            [$field, $type] = array_map('trim', explode(':', $definition, 2));
            $field = ltrim($field, "* \t\n\r\0\x0B");
            $optional = str_ends_with($field, '?');
            $fieldName = rtrim($field, '?');
            $types = $this->resolveTypeString($type);

            $this->definition->addField($fieldName, $types, $optional);
            $this->definition->addReferences($this->extractClassReferences($types));
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
            $types = $this->resolveTypeString($match[1] ?? 'mixed');

            $this->definition->addField($match[2], $types);
            $this->definition->addReferences($this->extractClassReferences($types));
        }
    }
}
