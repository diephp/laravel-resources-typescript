<?php

declare(strict_types=1);

namespace DiePHP\Tests;

use DiePHP\LaravelResourcesTypescript\Services\MakeResourcesStructure;
use DiePHP\LaravelResourcesTypescript\Services\TypeScriptInterfaceGenerator;
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
}
