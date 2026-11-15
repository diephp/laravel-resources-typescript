<?php

declare(strict_types=1);

namespace DiePHP\Tests;

use DiePHP\LaravelResourcesTypescript\Parsers\PhpDocParser;
use DiePHP\LaravelResourcesTypescript\Services\MakeResourcesStructure;
use DiePHP\LaravelResourcesTypescript\Services\ReferencedTypeResolver;
use DiePHP\LaravelResourcesTypescript\Services\TypeScriptInterfaceGenerator;
use DiePHP\Tests\Support\InheritedAnalyticsResource;
use PHPUnit\Framework\TestCase;

class TypeGenerationTest extends TestCase
{
    public function test_it_generates_interfaces_aliases_and_enum_types(): void
    {
        $config = require dirname(__DIR__) . '/src/config/resources2typescript.php';

        $structure = new MakeResourcesStructure(
            dirname(__DIR__) . '/tests/Examples',
            $config['parsers'],
            $config['fallback_parsers'],
        );

        $generator = new TypeScriptInterfaceGenerator($config['php_to_ts_type_map']);

        $generatedTypes = $generator->generate($structure->generate());

        $expected = <<<'TS'
export interface AddressResource {
  street: StreetResource;
  number: string;
  zip: number;
  zip2: any;
}

export interface AnalyticsShapeResource {
  series: Array<{ name: string; data: number[] }>;
  categories: string[];
}

export interface ApiMediaResource {
  id: number;
  type: string;
  thumbnails: { big: string | null; mini: string | null; small: string | null; medium: string | null; p320: string | null; p720: string | null };
  created_at: string;
}

export interface ApiMediaViaClassResource {
  id: number;
  thumbnails: MediaThumbnails;
  created_at: string;
}

export interface CategoryResource {
  id: number;
  name: string;
}

export interface ContactModel {
  name: any;
  email: any;
  phone: any;
}

export interface EnumFieldResource {
  status: StatusEnum;
  statusFromFactory: StatusEnum;
  nullableStatus: null;
  previousStatus?: StatusEnum;
}

export type EnumValueResource = StatusEnum;

export interface MalformedPhpDocResource {
  series: { name: string; data: number[] };
  categories: any;
}

export interface MediaThumbnails {
  mini: string | null;
  small: string | null;
  medium: string | null;
  big: string | null;
  p320: string | null;
  p720: string | null;
}

export interface ResourcePropertyHintsResource {
  name: any[];
  status: StatusEnum;
  categories: any[];
}

export interface SiteModel {
  domain: string;
  protocol: any;
}

export type StatusEnum = 'draft' | 'published' | 'archived';

export interface StreetResource {
  name: string;
  number: number;
}

export interface TestResource {
  id: number;
  uuid: string;
  type: string;
  user: UserDto;
  contact: ContactModel;
  category: CategoryResource;
  size: number;
  list: any[];
  address: AddressResource[];
  site: SiteModel;
  createdAt: TimeResource;
}

export interface TimeResource {
  time: any;
  time_zone?: any;
}

export interface UserDto {
  name: string;
  email: string;
  age: number;
}
TS;

        self::assertSame($expected . PHP_EOL, $generatedTypes);
    }

    public function test_phpdoc_generates_dictionaries_tuples_and_nested_enum_references(): void
    {
        $parser = new PhpDocParser();
        $definition = $parser->process(<<<'PHP'
            <?php

            namespace Example;

            use DiePHP\Tests\Examples\StatusEnum as Status;

            class ReportResource
            {
                /**
                 * @return array{
                 *     translations: array<string, string|null>,
                 *     changes: array<array-key, mixed>,
                 *     schedule?: list<array{string, int|null}>,
                 *     statuses: array<string, list<Status|null>>,
                 *     empty: array{},
                 * }
                 */
                public function toArray(): array {}
            }
            PHP);

        $definitions = (new ReferencedTypeResolver())->resolve(['ReportResource' => $definition]);
        $generator = new TypeScriptInterfaceGenerator();

        self::assertSame(<<<'TS'
            export interface ReportResource {
              translations: Record<string, string | null>;
              changes: Record<string, unknown> | Array<unknown>;
              schedule?: Array<[string, number | null]>;
              statuses: Record<string, Array<StatusEnum | null>>;
              empty: [];
            }

            export type StatusEnum = 'draft' | 'published' | 'archived';
            TS, rtrim($generator->generate($definitions)));
    }

    public function test_phpdoc_generates_a_type_alias_for_a_generic_return_array(): void
    {
        $definition = (new PhpDocParser())->process(<<<'PHP'
            <?php

            class ChangesResource
            {
                /**
                 * @return array<
                 *     array-key,
                 *     list<string|null>
                 * > The JSON changes.
                 */
                public function toArray(): array {}
            }
            PHP);

        self::assertSame(
            'export type ChangesResource = Record<string, Array<string | null>> | Array<Array<string | null>>;',
            rtrim((new TypeScriptInterfaceGenerator())->generate(['ChangesResource' => $definition])),
        );
    }

    public function test_phpdoc_inherits_the_parent_serializer_shape(): void
    {
        $resource = new \ReflectionClass(InheritedAnalyticsResource::class);
        $definition = (new PhpDocParser())->process(file_get_contents($resource->getFileName()));

        self::assertSame('InheritedAnalyticsResource', $definition->getName());
        self::assertSame(['series', 'categories'], array_keys($definition->getFields()));
    }
}
