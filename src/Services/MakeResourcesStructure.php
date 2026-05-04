<?php

declare(strict_types=1);

namespace DiePHP\LaravelResourcesTypescript\Services;

use DiePHP\LaravelResourcesTypescript\Dtos\TypeDefinition;
use Throwable;

class MakeResourcesStructure
{
    /**
     * @param list<class-string> $parsers
     * @param list<class-string> $fallbackParsers
     */
    public function __construct(
        private string $resourcesDir,
        private array $parsers = [],
        private array $fallbackParsers = [],
        private ?ReferencedTypeResolver $referencedTypeResolver = null,
    ) {
        $this->referencedTypeResolver ??= new ReferencedTypeResolver();
    }

    /**
     * @return array<string, TypeDefinition>
     */
    public function generate(): array
    {
        $definitions = [];

        foreach ($this->findPhpFiles() as $filePath) {
            $className = pathinfo($filePath, PATHINFO_FILENAME);
            $definition = $this->parseFile($filePath, $className);

            if ($definition->isEmpty()) {
                $definition = $this->alternativeParseFile($filePath, $className);
            }

            $definitions[$className] = $definition;
        }

        ksort($definitions);

        return $this->referencedTypeResolver->resolve($definitions);
    }

    /**
     * @return list<string>
     */
    private function findPhpFiles(): array
    {
        if (!is_dir($this->resourcesDir)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->resourcesDir, \FilesystemIterator::SKIP_DOTS),
        );

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    private function parseFile(string $filePath, string $className): TypeDefinition
    {
        $code = file_get_contents($filePath);

        if ($code === false) {
            return TypeDefinition::interface($className);
        }

        $definition = TypeDefinition::interface($className);

        foreach ($this->parsers as $parserClass) {
            try {
                $parser = new $parserClass();
                $definition = $definition->merge($parser->process($code, $className));
            } catch (Throwable) {
                continue;
            }
        }

        return $definition;
    }

    private function alternativeParseFile(string $filePath, string $className): TypeDefinition
    {
        $code = file_get_contents($filePath);

        if ($code === false) {
            return TypeDefinition::interface($className);
        }

        $definition = TypeDefinition::interface($className);

        foreach ($this->fallbackParsers as $parserClass) {
            try {
                $parser = new $parserClass();
                $definition = $definition->merge($parser->process($code, $className));
            } catch (Throwable) {
                continue;
            }
        }

        return $definition;
    }
}
