<?php

namespace DiePHP\LaravelResourcesTypescript\Parsers;

use PhpParser\Node;
use PhpParser\Node\Stmt\Namespace_;

/**
 * Class PhpDocParser
 *
 * A specialized parser for traversing and analyzing nodes to extract type
 * information from PHPDoc comments. This class identifies and processes
 * PHPDoc annotations such as @return and @property, and maps them to
 * corresponding field definitions.
 */
class PhpDocParser extends AbstractParser
{
    private array $fields = [];

    /**
     * Processes a given node during traversal and extracts type information
     * from PHPDoc comments associated with classes and methods.
     *
     * @param Node $node The node being traversed and analyzed.
     * @return void This method does not return a value.
     */
    public function enterNode(Node $node) : void
    {
        $namespace = '';
        if ($node instanceof Namespace_) {
            $namespace = $node->name->toString() . '\\';
        }

        if ($node instanceof Node\Stmt\ClassMethod && $node->name->toString() === 'toArray' && $node->getDocComment()) {
            if ($docComment = $node->getDocComment()->getText()) {
                if (preg_match('/@return\s+array{([^}]+)}/', $docComment, $matches)) {
                    $typeDefinitions = explode(',', $matches[1]);
                    foreach ($typeDefinitions as $definition) {
                        if (preg_match('/\s*([\w]+):\s*([\w\\\\]+)/', $definition, $match)) {
                            $this->fields[$match[1]] = $this->convertToPhpType($match[2] ?? 'mixed', $namespace);
                        }
                    }
                }
            }
        }

        if ($node instanceof Node\Stmt\Class_) {
            $docComment = $node->getDocComment();
            if ($docComment) {
                $matches = [];
                if (preg_match_all('/@property\s+([\w\\\\]+)\s+\$([\w]+)/i', $docComment->getText(), $matches)) {
                    foreach ($matches[2] as $index => $fieldName) {
                        $this->fields[$fieldName] = $this->convertToPhpType($matches[1][$index] ?? 'mixed', $namespace);
                    }
                }

                if (preg_match_all('/@property\s+\$(\w+)/i', $docComment->getText(), $matches)) {
                    foreach ($matches[1] as $index => $fieldName) {
                        $this->fields[$fieldName] = 'mixed';
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
