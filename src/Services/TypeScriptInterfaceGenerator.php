<?php

namespace DiePHP\LaravelResourcesTypescript\Services;

/**
 * A class for generating TypeScript interface definitions from resource data
 * and optionally persisting them to a file. This class maps PHP data types
 * to their corresponding TypeScript types via an internal type map.
 */
class TypeScriptInterfaceGenerator
{
    private array $phpToTsTypeMap;

    public function __construct(array $phpToTsTypeMap = [])
    {
        $this->phpToTsTypeMap = $phpToTsTypeMap;
    }

    /**
     * Generates TypeScript interface definitions from the provided resource data.
     *
     * @param array $resourcesData An associative array where keys represent the interface names
     *                              and values are arrays defining the fields and their types.
     * @return string A string containing TypeScript interface definitions.
     */
    public function generate(array $resourcesData): string
    {
        $output = "";
        foreach ($resourcesData as $name => $fields) {
            $output .= "export interface $name {" . PHP_EOL;
            foreach ($fields as $key => $type) {

                if (is_array($type)) {
                    $type = implode(' | ',
                        array_unique(
                            array_map(function ($item) {
                                return $this->phpToTsTypeMap[$item] ?? $item;
                            }, $type)
                        )
                    );
                } else {
                    $type = $this->phpToTsTypeMap[$type] ?? $type;
                }

                $output .= "  {$key}: {$type};" . PHP_EOL;
            }
            $output .= "}" . PHP_EOL . PHP_EOL;
        }

        return $output;
    }

    /**
     * Saves the provided content to the specified file path.
     * Creates necessary directories if they do not exist.
     *
     * @param string $content The content to be written to the file.
     * @param string $path The file path where the content should be saved.
     * @return false|int Returns the number of bytes written to the file, or false on failure.
     */
    public function store(string $content, string $path): false|int
    {
        if (!file_exists(dirname($path)))
            mkdir(dirname($path), 0777, true);

        return file_put_contents($path, $content);
    }
}
