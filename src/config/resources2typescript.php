<?php

use DiePHP\LaravelResourcesTypescript\Parsers\ArrayShapeParser;
use DiePHP\LaravelResourcesTypescript\Parsers\ExecToArrayParser;
use DiePHP\LaravelResourcesTypescript\Parsers\FillableParser;
use DiePHP\LaravelResourcesTypescript\Parsers\PhpDocParser;
use DiePHP\LaravelResourcesTypescript\Parsers\ToArrayParser;

return [

    'resources_dir' => 'app/Http/Resources',

    'output_typescript_file' => 'resources/ts/Resources.ts',

    'php_to_ts_type_map' => [
        'int' => 'number',
        'integer' => 'number',
        'float' => 'number',
        'double' => 'number',
        'bool' => 'boolean',
        'boolean' => 'boolean',
        'string' => 'string',
        'array' => 'any[]',
        'object' => 'object',
        'mixed' => 'any',
        'null' => 'null',
    ],

    'parsers' => [
        ArrayShapeParser::class,
        PhpDocParser::class,
        ToArrayParser::class,
        FillableParser::class,
    ],

    'fallback_parsers' => [
        ExecToArrayParser::class,
    ],

];
