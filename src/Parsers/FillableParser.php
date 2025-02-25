<?php

namespace DiePHP\LaravelResourcesTypescript\Parsers;

use PhpParser\Node;

/**
 * Class FillableParser
 *
 * Parses nodes from an abstract syntax tree (AST) to identify and process "fillable" properties.
 * These properties are analyzed and their values are stored for further use or validation.
 */
class FillableParser extends AbstractParser
{
    private array $fields = [];

    /**
     * Processes a given node in the abstract syntax tree (AST) to analyze specific properties
     * and update internal fields when conditions are met.
     *
     * @param Node $node The node to process. Expected to be an AST node, such as a property node.
     * @return void
     */
    public function enterNode(Node $node)
    {
        if ($node instanceof Node\Stmt\Property && $node->isProtected()) {
            foreach ($node->props as $prop) {
                if ($prop->name->toString() === 'fillable' && $prop->default instanceof Node\Expr\Array_) {
                    foreach ($prop->default->items as $item) {
                        if ($item->value instanceof Node\Scalar\String_) {
                            $this->fields[$item->value->value] = 'any';
                        }
                    }
                }
            }
        }
    }

    protected function getFields(): array
    {
        return $this->fields;
    }
}
