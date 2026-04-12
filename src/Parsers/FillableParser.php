<?php

declare(strict_types=1);

namespace DiePHP\LaravelResourcesTypescript\Parsers;

use DiePHP\LaravelResourcesTypescript\Dtos\TypeDefinition;
use PhpParser\Node;

class FillableParser extends AbstractParser
{
    private TypeDefinition $definition;

    public function enterNode(Node $node): null|int|Node|array
    {
        parent::enterNode($node);

        if (!$node instanceof Node\Stmt\Property || !$node->isProtected()) {
            return null;
        }

        foreach ($node->props as $prop) {
            if (
                $prop->name->toString() !== 'fillable'
                || !$prop->default instanceof Node\Expr\Array_
            ) {
                continue;
            }

            foreach ($prop->default->items as $item) {
                if ($item?->value instanceof Node\Scalar\String_) {
                    $this->definition->addField($item->value->value, 'mixed');
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
