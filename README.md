# Laravel Resources to TypeScript

Version `2.0` of the package.

Generate TypeScript `interface` and `type` definitions from standard Laravel resources without rewriting your API layer into DTO-only abstractions.

The package analyzes native Laravel resources and can extract structure from:

- direct `return []` arrays
- PHPDoc array shapes
- `#[ArrayShape(...)]`
- public typed properties on DTO-style classes
- Eloquent `$fillable`
- `toArray()` methods that build the response through a variable like `$data['field'] = ...; return $data;`
- resources and fields that return PHP `enum`

If a type cannot be determined safely, it falls back to `any`.

## Project Story

This is a lightweight package for development-time generation of TypeScript interfaces from standard Laravel resources for Laravel `10`, `11`, `12`, `13` and PHP `8.1+`.

It was originally developed to satisfy a requirement to minimize the use of heavy packages in the [Restsify](https://restsify.com) project, and then opened for everyone who prefers to keep server resources fully under control.

## What's New in 2.0

- Support for PHP `enum` inside resources.
- Generation of TypeScript `type` aliases for PHP enums.
- Support for resources that return enum as the root value, not only arrays.
- Better AST analysis for `toArray()` methods that build payload through a variable like `$data = []; $data['x'] = ...; return $data;`.
- Support for optional fields created in conditional branches.
- Support for DTO-like classes with public typed properties.
- Recursive scanning of PHP files inside the configured resources directory.
- Cleaner TypeScript output generation with both `interface` and `type`.

## Compatibility

- PHP: `^8.1`
- Laravel: tested with `^9.0` and compatible with newer versions `^13.0` that keep standard resource behavior
- TypeScript output: supports both generated `interface` and `type`

## Upgrading from 1.x

Version `2.0` is mostly source-compatible in usage, but there are a few important changes.

### Breaking changes

- Minimum PHP version is now `8.1` instead of `8.0`.
- Generated output is no longer limited to `interface`. Enum-based definitions can now be emitted as TypeScript `type`.
- In some resources the generated types may become more precise than in `1.x`.
  For example:
  - enum fields now reference generated enum types
  - conditionally assigned fields can become optional
  - DTO public typed properties are now inferred instead of falling back to `any`

### What remains compatible

- Standard Laravel `JsonResource` with `return []`.
- `#[ArrayShape(...)]`.
- PHPDoc `@return array{...}`.
- Nested resources and resource collections.
- Fallback to `any` when the package cannot safely infer a type.

## Installation

```bash
composer require --dev diephp/laravel-resources-typescript
```

For `2.0`, your project must run on PHP `8.1+`.

Publish the config if you want to customize paths:

```bash
php artisan vendor:publish --tag=resources2typescript
```

## Usage

Run the generator:

```bash
php artisan diephp:generate-typescript-interfaces
```

Default config:

```php
'resources_dir' => 'app/Http/Resources',
'output_typescript_file' => 'resources/ts/Resources.ts',
```

## Supported Type Sources

The generator can infer structure from:

- direct `return []`
- `#[ArrayShape(...)]`
- PHPDoc `@return array{...}`
- assignments through a temporary variable inside `toArray()`
- public typed properties on DTO classes
- model PHPDoc properties
- model `$fillable`
- enum values returned by fields or by the resource itself

## Examples

### 1. Plain resource with casts

```php
class ExampleResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => (int) $this->id,
            'name' => (string) $this->name,
        ];
    }
}
```

```ts
export interface ExampleResource {
  id: number;
  name: string;
}
```

### 2. Resource with `ArrayShape`

```php
use JetBrains\PhpStorm\ArrayShape;

class ExampleResource extends JsonResource
{
    #[ArrayShape(['id' => 'int', 'name' => 'string'])]
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
        ];
    }
}
```

```ts
export interface ExampleResource {
  id: number;
  name: string;
}
```

### 3. Resource with PHPDoc array shape

```php
class ExampleResource extends JsonResource
{
    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     category: \App\Http\Resources\CategoryResource,
     * }
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'category' => new CategoryResource($this->category),
        ];
    }
}
```

```ts
export interface ExampleResource {
  id: number;
  name: string;
  category: CategoryResource;
}
```

### 4. DTO or model-like class with typed public properties

```php
class UserDto
{
    public string $name;
    public string $email;
    public int $age;
}
```

```ts
export interface UserDto {
  name: string;
  email: string;
  age: number;
}
```

### 5. Model with PHPDoc properties or `$fillable`

```php
/**
 * @property string $domain
 * @property mixed $protocol
 */
class SiteModel extends Model
{
}
```

```php
class ContactModel extends Model
{
    protected $fillable = ['name', 'email', 'phone'];
}
```

```ts
export interface SiteModel {
  domain: string;
  protocol: any;
}

export interface ContactModel {
  name: any;
  email: any;
  phone: any;
}
```

### 6. Resource built through a variable

```php
class TimeResource extends JsonResource
{
    public function toArray($request): array
    {
        $result = [];
        $result['time'] = $this->resource->time;

        if ($this->resource->time_zone) {
            $result['time_zone'] = $this->resource->time_zone;
        }

        return $result;
    }
}
```

```ts
export interface TimeResource {
  time: any;
  time_zone?: any;
}
```

### 7. Enum field inside a resource

```php
enum StatusEnum: string
{
    case Draft = 'draft';
    case Published = 'published';
}

class StatusResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'status' => StatusEnum::Published,
        ];
    }
}
```

```ts
export type StatusEnum = 'draft' | 'published';

export interface StatusResource {
  status: StatusEnum;
}
```

### 8. Resource that returns enum as root value

```php
class EnumValueResource extends JsonResource
{
    public function toArray($request)
    {
        return StatusEnum::Published;
    }
}
```

```ts
export type EnumValueResource = StatusEnum;
```

## Notes

- Priority is roughly: `ArrayShape` -> PHPDoc -> AST analysis of `toArray()` -> runtime fallback.
- Enum definitions are generated as TypeScript `type` aliases.
- Nested resources are preserved as references to their generated TypeScript definitions.
- The scanner walks resource directories recursively.
- If the package cannot infer an exact type, it falls back to `any`.

## Search Keywords

If you are publishing or searching for the package, the main focus of `2.0` is:

- Laravel Resource to TypeScript
- Laravel JsonResource TypeScript generator
- PHP enum to TypeScript
- API resource typings for Laravel

## Testing

```bash
composer test
```

## License

MIT
