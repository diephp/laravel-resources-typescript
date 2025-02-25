<?php

namespace DiePHP\LaravelResourcesTypescript\Parsers;

use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeVisitorAbstract;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;

/**
 * An abstract base class for parsing source code and traversing abstract syntax trees (AST).
 */
abstract class AbstractParser extends NodeVisitorAbstract
{
    /**
     * Parses the provided code and traverses the abstract syntax tree (AST) to extract fields.
     *
     * @param string $code The source code to be parsed.
     * @return array The extracted fields from the source code.
     */
    public function process(string $code): array
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $ast = $parser->parse($code);

        $traverser = new NodeTraverser();
        $traverser->addVisitor($this);
        $traverser->traverse($ast);

        return $this->getFields();
    }

    /**
     * Parses and retrieves the type from the given node as a string.
     *
     * @param mixed $node The node to parse, expected to potentially be an instance of String_.
     * @return string The type extracted from the node, or 'mixed' if not identifiable.
     */
    protected function parseTypeFromString($node): string
    {
        if ($node instanceof String_) {
            return $node->value;
        }
        return 'mixed';
    }

    /**
     * Infers and returns the type of the given node as a string.
     *
     * @param mixed $node The node from which to infer the type. It can represent various expressions or constructs.
     * @param string $namespace The namespace to use for resolving class names in specific cases.
     * @return string The inferred type as a string, or 'mixed' if the type cannot be determined.
     */
    protected function inferType($node, string $namespace = ''): string
    {

        if ($node instanceof \PhpParser\Node\Expr\Cast\Int_) {
            return 'int';
        } elseif ($node instanceof \PhpParser\Node\Expr\Cast\String_) {
            return 'string';
        } elseif ($node instanceof \PhpParser\Node\Expr\Cast\Double) {
            return 'float';
        } elseif ($node instanceof \PhpParser\Node\Expr\Cast\Bool_) {
            return 'bool';
        } elseif ($node instanceof \PhpParser\Node\Expr\Cast\Array_) {
            return 'array';
        } elseif ($node instanceof \PhpParser\Node\Expr\ConstFetch && $node->name->toLowerString() === 'null') {
            return 'null';
        } elseif ($node instanceof \PhpParser\Node\Expr\New_) {
            switch ($node->class->name) {
                case 'Collection' :
                    return "any[]";
                case 'Carbon':
                    return "string";
                default:
                    return $this->convertToPhpType($node->class->toString(), $namespace);
            }

        } elseif ($node instanceof StaticCall) {
            switch ($node->name->toString()) {
                case 'collection':
                    return $this->convertToPhpType($node->class->toString(), $namespace) . "[]";
                case 'now':
                    return "string";
                default:
                    return 'mixed';
            }
        } elseif ($node instanceof \PhpParser\Node\Expr\Cast\Object_) {
            return 'object';
        } else if ($node instanceof \PhpParser\Node\Expr\Array_) {
            return 'array';
        }

        return 'mixed';
    }


    /**
     * Converts a fully qualified PHP type to its base class name, optionally removing a given namespace.
     *
     * @param string $phpType The fully qualified PHP type, including namespace, to be converted.
     * @param string $namespaceToRemove An optional namespace to remove from the type, if present.
     * @return string The base class name portion of the given PHP type.
     */
    protected function convertToPhpType(string $phpType, string $namespaceToRemove = ''): string
    {
        if (str_starts_with($phpType, '\\')) {
            $phpType = trim($phpType, '\\');
        }

        if ($namespaceToRemove && str_starts_with($phpType, $namespaceToRemove)) {
            $phpType = substr($phpType, strlen($namespaceToRemove));
        }

        return class_basename($phpType);
    }

    /**
     * Retrieves an array of fields.
     *
     * @return array An array of fields.
     */
    abstract protected function getFields(): array;
}
