<?php

declare(strict_types=1);

namespace DiePHP\LaravelResourcesTypescript\Parsers;

use DiePHP\LaravelResourcesTypescript\Dtos\TypeDefinition;
use PhpParser\Node;
use PhpParser\Node\NullableType;
use PhpParser\Node\UnionType;

class PublicPropertyParser extends AbstractParser
{
    private TypeDefinition $definition;

    public function enterNode(Node $node): null|int|Node|array
    {
        parent::enterNode($node);

        if (!$node instanceof Node\Stmt\Property || !$node->isPublic()) {
            return null;
        }

        $types = ['mixed'];
        $optional = false;

        if ($node->type instanceof NullableType) {
            $types = $this->mergeTypeLists(
                $this->resolveTypeString($node->type->type->toString()),
                ['null'],
            );
        } elseif ($node->type instanceof UnionType) {
            $types = ['mixed'];

            foreach ($node->type->types as $typeNode) {
                $types = $this->mergeTypeLists($types, $this->resolveTypeString($typeNode->toString()));
            }
        } elseif ($node->type !== null) {
            $types = $this->resolveTypeString($node->type->toString());
        } else {
            $optional = true;
        }

        foreach ($node->props as $property) {
            $this->definition->addField($property->name->toString(), $types, $optional);
            $this->definition->addReferences($this->extractClassReferences($types));
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
}
