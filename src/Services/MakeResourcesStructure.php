<?php

namespace DiePHP\LaravelResourcesTypescript\Services;

/**
 * Represents a utility for constructing a resource structure by scanning a directory,
 * parsing PHP files, and categorizing their contents based on extracted data.
 * This class supports multiple parsers and fallback options for robust data extraction.
 */
class MakeResourcesStructure
{
    private string $resourcesDir;
    private array $parsers;
    private array $fallbackParsers = [];

    public function __construct(string $resourcesDir, array $parsers = [], array $fallbackParsers = [])
    {
        $this->resourcesDir = $resourcesDir;
        $this->parsers = $parsers;
        $this->fallbackParsers = $fallbackParsers;
    }

    /**
     * Scans the specified resources directory for PHP files, parses their data,
     * and returns an associative array where the keys are class names derived
     * from file names and the values are the corresponding parsed data.
     *
     * @return array An associative array mapping class names to parsed data.
     */
    public function generate(): array
    {
        $resourceFiles = glob("{$this->resourcesDir}/*.php");
        $resourcesData = [];

        foreach ($resourceFiles as $filePath) {
            $className = pathinfo($filePath, PATHINFO_FILENAME);

            if (!$data = $this->parseFile($filePath)) {
                $data = $this->alternativeParseFile($filePath);
            }

            $resourcesData[$className] = $data;
        }

        return $resourcesData;
    }

    /**
     * Parses the contents of a given file using multiple parsers and extracts data fields.
     * Each parser is instantiated and executed to process the file's code, and their results
     * are merged recursively into a unified array. Errors encountered during parsing are logged.
     *
     * @param string $filePath The path to the file to be parsed.
     * @return array An array containing the extracted data fields from the file.
     */
    private function parseFile(string $filePath): array
    {
        $code = file_get_contents($filePath);
        $fields = [];

        foreach ($this->parsers as $parser) {
            try {
                $parserInstance = new $parser();
                $fields = array_merge_recursive($fields, $parserInstance->process($code));
            } catch (\Exception $e) {
                echo "⚠ Parsers error: {$e->getMessage()}\n";
            }
        }

        return $fields;
    }

    /**
     * Parses a file using a set of fallback parsers to extract structured data.
     *
     * @param string $filePath The path to the file that needs to be parsed.
     * @return array An array containing the parsed and merged data fields from the provided file.
     */
    private function alternativeParseFile(string $filePath): array
    {
        $code = file_get_contents($filePath);
        $fields = [];

        foreach ($this->fallbackParsers as $parser) {
            try {
                $parserInstance = new $parser();
                $fields = array_merge_recursive($fields, $parserInstance->process($code));
            } catch (\Exception $e) {
                echo "⚠ Fallback parser error: {$e->getMessage()}\n";
            }
        }

        return $fields;
    }
}
