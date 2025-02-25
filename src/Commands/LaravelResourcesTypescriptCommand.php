<?php

namespace DiePHP\LaravelResourcesTypescript\Commands;


use DiePHP\LaravelResourcesTypescript\Services\MakeResourcesStructure;
use DiePHP\LaravelResourcesTypescript\Services\TypeScriptInterfaceGenerator;
use Illuminate\Console\Command;

/**
 * Class responsible for generating TypeScript interfaces based on Laravel resources.
 */
class LaravelResourcesTypescriptCommand extends Command
{
    /**
     * The signature of the command used to generate TypeScript interfaces from PHP code.
     */
    protected $signature = 'diephp:generate-typescript-interfaces';

    /**
     * The description of the command that generates TypeScript interfaces from Laravel resources.
     */
    protected $description = 'Generate typescript interfaces from laravel resources';

    /**
     * Handles the generation of TypeScript interfaces from PHP resources.
     *
     * This method retrieves configuration settings, processes the resources
     * to extract data structures, and generates TypeScript interfaces based
     * on the extracted information. The resulting TypeScript code is stored
     * in a specified output file.
     *
     * @return int Command execution status, indicating success or failure.
     */
    public function handle()
    {
        $resourcesDir = config('resources2typescript.resources_dir');
        $parsers = config('resources2typescript.parsers');
        $fallbackParsers = config('resources2typescript.fallback_parsers');
        $outputFile = config('resources2typescript.output_typescript_file');
        $typeMapping = config('resources2typescript.php_to_ts_type_map');

        $generator = new MakeResourcesStructure($resourcesDir, $parsers, $fallbackParsers);
        $typeScriptInterfaceGenerator = new TypeScriptInterfaceGenerator($typeMapping);

        if ($resourcesData = $generator->generate()) {
            $tsCode = $typeScriptInterfaceGenerator->generate($resourcesData);
            $typeScriptInterfaceGenerator->store($tsCode, $outputFile);
        }

        $this->info("✅ TypeScript interfaces generated in {$outputFile}}\n");

        return Command::SUCCESS;
    }


}