<?php

declare(strict_types=1);

namespace DiePHP\LaravelResourcesTypescript\Parsers;

use DiePHP\LaravelResourcesTypescript\Dtos\TypeDefinition;
use PhpParser\Node;

class ArrayShapeParser extends AbstractParser
{
    private TypeDefinition $definition;

    public function enterNode(Node $node): null|int|Node|array
    {
        parent::enterNode($node);

        if (!$node instanceof Node\AttributeGroup) {
            return null;
        }

        foreach ($node->attrs as $attribute) {
            if ($this->shortName($attribute->name->toString()) !== 'ArrayShape') {
                continue;
            }

            foreach ($attribute->args as $arg) {
                if (!$arg->value instanceof Node\Expr\Array_) {
                    continue;
                }

                foreach ($arg->value->items as $item) {
                    if (!$item?->key instanceof Node\Scalar\String_) {
                        continue;
                    }

                    $types = $this->parseTypeFromString($item->value);
                    $this->definition->addField($item->key->value, $types);
                    $this->definition->addReferences($this->extractClassReferences($types));
                }
            }
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
