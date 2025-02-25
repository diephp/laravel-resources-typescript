<?php

namespace DiePHP\LaravelResourcesTypescript\Parsers;

use DiePHP\LaravelResourcesTypescript\Dtos\GodDto;
use Illuminate\Http\Request;
use ReflectionClass;
use ReflectionException;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;

/**
 * The ExecToArrayParser class is responsible for parsing PHP code to identify the namespace and class name,
 * and performing reflection-based operations to process and analyze the class.
 */
class ExecToArrayParser extends AbstractParser
{
    private ?string $namespace = null;
    private ?string $className = null;
    private array $fields = [];

    /**
     * Processes the provided PHP code, parses its namespace and class name, and attempts to execute reflection-based operations on it.
     *
     * @param string $code The PHP code to be parsed and analyzed.
     * @return array The resulting array of fields processed from the given class, or an empty array if the class is not found or cannot be processed.
     */
    public function process(string $code): array
    {
        // Parse namespace and class name
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $ast = $parser->parse($code);

        $traverser = new NodeTraverser();
        $traverser->addVisitor($this);
        $traverser->traverse($ast);

        if ($this->namespace && $this->className) {
            $fullClassName = "{$this->namespace}\\{$this->className}";

            // Ensure class is available before reflection
            if (!class_exists($fullClassName)) {
                $this->attemptRequireFile($fullClassName);
            }

            if (class_exists($fullClassName)) {
                try {
                    $this->fields = $this->executeToArray($fullClassName);
                } catch (ReflectionException $e) {

                }
            } else {
                echo "⚠ Warning: Class \"$fullClassName\" does not exist. Run `composer dump-autoload` if necessary.\n";
            }
        }

        return $this->fields;
    }

    /**
     * Processes a given node and extracts namespace or class name information.
     *
     * @param Node $node The node to be processed. It may represent a namespace or a class statement.
     * @return void
     */
    public function enterNode(Node $node): void
    {
        if ($node instanceof Node\Stmt\Namespace_) {
            $this->namespace = $node->name->toString();
        }

        if ($node instanceof Node\Stmt\Class_) {
            $this->className = $node->name->toString();
        }
    }

    /**
     * Executes the toArray method of a specified class instance if available, and generates an associative array
     * with the same keys as the result, filled with 'mixed' as the value type.
     *
     * @param string $fullClassName The fully qualified class name to instantiate and execute the toArray method on.
     * @return array An associative array with keys from the toArray method's output and 'mixed' as the values. Returns an empty array if an error occurs or toArray is not available.
     */
    private function executeToArray(string $fullClassName): array
    {
        try {
            $reflectionClass = new ReflectionClass($fullClassName);
            $constructor = $reflectionClass->getConstructor();

            // Create an instance of the class
            $instance = $constructor && $constructor->getNumberOfRequiredParameters() > 0
                ? $reflectionClass->newInstance(new GodDto())
                : $reflectionClass->newInstance();

            // Call toArray() if available
            if (method_exists($instance, 'toArray')) {
                $data = $instance->toArray(new Request());
                if (is_array($data)) {
                    return array_fill_keys(array_keys($data), 'mixed');
                }
            }
        } catch (ReflectionException $e) {
            echo "⚠ Reflection error: {$e->getMessage()}\n";
        }

        return [];
    }

    /**
     * Attempts to require the file corresponding to the given fully qualified class name.
     *
     * @param string $fullClassName The fully qualified class name to locate and require.
     * @return void
     */
    private function attemptRequireFile(string $fullClassName): void
    {
        $path = str_replace('\\', DIRECTORY_SEPARATOR, $fullClassName) . '.php';
        if (file_exists($path)) {
            require_once $path;
        }
    }

    protected function getFields(): array
    {
        return $this->fields;
    }
}
