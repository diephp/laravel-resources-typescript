<?php

namespace DiePHP\LaravelResourcesTypescript\Parsers;

use PhpParser\Node;
use PhpParser\Node\Stmt\Namespace_;

/**
 * A parser class for extracting and analyzing the structure of `toArray` methods in PHP code.
 *
 * This class traverses nodes within the abstract syntax tree (AST) to identify and process
 * the `toArray` method within a class. It captures the keys and associated types of fields
 * returned by the `toArray` method's array structure.
 */
class ToArrayParser extends AbstractParser
{
    private array $fields = [];

    /**
     * Processes a given node to extract information, particularly focusing on array return statements
     * in methods named 'toArray'. The method analyzes the structure of the node and infers types for
     * elements within arrays.
     *
     * @param Node $node The node to be processed. This can represent various syntactical constructs
     *                    like namespaces, class methods, and array structures in PHP code.
     * @return void
     */
    public function enterNode(Node $node)
    {
        $namespace = '';
        if ($node instanceof Namespace_) {
            $namespace = $node->name->toString() . '\\';
        }

        if ($node instanceof Node\Stmt\ClassMethod && $node->name->toString() === 'toArray') {
            foreach ($node->getStmts() as $stmt) {
                if ($stmt instanceof Node\Stmt\Return_ && $stmt->expr instanceof Node\Expr\Array_) {
                    foreach ($stmt->expr->items as $item) {
                        if ($item->key instanceof Node\Scalar\String_) {
                            $key = $item->key->value;
                            $type = $this->inferType($item->value, $namespace);
                            $this->fields[$key] = $type;
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
