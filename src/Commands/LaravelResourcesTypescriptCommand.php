<?php

namespace DiePHP\LaravelResourcesTypescript\Commands;


use DiePHP\LaravelResourcesTypescript\Services\MakeResourcesStructure;
use DiePHP\LaravelResourcesTypescript\Services\TypeScriptInterfaceGenerator;
use Illuminate\Console\Command;

class LaravelResourcesTypescriptCommand extends Command
{
    protected $signature = 'diephp:generate-typescript-interfaces';
    protected $description = 'Generate typescript interfaces from laravel resources';

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