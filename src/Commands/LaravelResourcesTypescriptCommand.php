<?php

declare(strict_types=1);

namespace DiePHP\LaravelResourcesTypescript\Commands;

use DiePHP\LaravelResourcesTypescript\Services\MakeResourcesStructure;
use DiePHP\LaravelResourcesTypescript\Services\TypeScriptInterfaceGenerator;
use Illuminate\Console\Command;

class LaravelResourcesTypescriptCommand extends Command
{
    protected $signature = 'diephp:generate-typescript-interfaces';

    protected $description = 'Generate TypeScript definitions from Laravel resources';

    public function handle(): int
    {
        $resourcesDir = (string) config('resources2typescript.resources_dir');
        $parsers = (array) config('resources2typescript.parsers', []);
        $fallbackParsers = (array) config('resources2typescript.fallback_parsers', []);
        $outputFile = (string) config('resources2typescript.output_typescript_file');
        $typeMapping = (array) config('resources2typescript.php_to_ts_type_map', []);

        $generator = new MakeResourcesStructure($resourcesDir, $parsers, $fallbackParsers);
        $typeScriptGenerator = new TypeScriptInterfaceGenerator($typeMapping);
        $resourcesData = $generator->generate();

        if ($resourcesData === []) {
            $this->warn(sprintf('No PHP resources were found in "%s".', $resourcesDir));

            return self::SUCCESS;
        }

        $typeScriptGenerator->store(
            $typeScriptGenerator->generate($resourcesData),
            $outputFile,
        );

        $this->info(sprintf('TypeScript definitions generated in %s', $outputFile));

        return self::SUCCESS;
    }
}
