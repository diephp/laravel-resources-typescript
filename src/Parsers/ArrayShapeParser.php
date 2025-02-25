<?php

namespace DiePHP\LaravelResourcesTypescript\Parsers;

use PhpParser\Node;
use PhpParser\Node\Stmt\Namespace_;

/**
 * The ArrayShapeParser class is responsible for parsing and mapping attribute information
 * into fields, specifically focusing on nodes that use the "ArrayShape" attribute. It extends
 * the functionality of the AbstractParser class.
 */
class ArrayShapeParser extends AbstractParser
{
    private array $fields = [];

    /**
     * Processes a given node, particularly handling specific node types to parse and
     * map attribute information into fields.
     *
     * @param Node $node The node being entered and processed. Can be of various types
     *                   including Namespace_ or Node\AttributeGroup, among others.
     * @return void This method does not return any value.
     */
    public function enterNode(Node $node): void
    {
        $namespace = '';
        if ($node instanceof Namespace_) {
            $namespace = $node->name->toString() . '\\';
        }

        if ($node instanceof Node\AttributeGroup) {
            foreach ($node->attrs as $attribute) {
                if ($attribute->name->toString() === 'ArrayShape') {
                    foreach ($attribute->args as $arg) {
                        if ($arg->value instanceof Node\Expr\Array_) {
                            foreach ($arg->value->items as $item) {
                                if ($item->key instanceof Node\Scalar\String_) {
                                    $key = $item->key->value;
                                    $this->fields[$key] = $this->convertToPhpType($this->parseTypeFromString($item->value), $namespace);
                                }
                            }
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
