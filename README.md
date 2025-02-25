# Laravel Resources to TypeScript Interfaces

A package to generate TypeScript interfaces from **STANDARD** Laravel Resource files, which are commonly used in API responses.

If you don’t want to sacrifice performance by installing large packages that do the same thing but require a specific file structure, DTOs, ValueObjects, and rewriting everything using new objects from scratch, or if you already have a project that uses standard Laravel Resources to format API responses, then this package is for you.
I created it because Laravel Resources are more than sufficient for building REST API applications.
I am testing this package in my own applications, and so far, it covers all the use cases I’ve encountered. If you have a scenario where it falls short, feel free to let me know.


## Installation

To install the package, use the following Composer command:

```bash
composer require diephp/laravel-resources-typescript
```

## Configuration

### Laravel 11+

If you are using **Laravel 11** or newer, you need to manually register the service provider. Add the following provider to your `bootstrap/providers.php` file:

```php
\DiePHP\LaravelResourcesTypescript\Providers\LaravelResourcesTypescriptProvider::class,
```
### Setup config 
config/resources2typescript.php

*change `output_typescript_file`* for your real path
```code
// Directory with laravel resources
'resources_dir' => 'app/Http/Resources', 

// Final typescript file with interfaces
'output_typescript_file' => 'resources/ts/Resources.ts', 

// Array of parces, you can remove or add yours parcels
'parsers' => [
    ArrayShapeParser::class, // parce ArrayShape signature
    PhpDocParser::class, // parce phpdoc signature
    ToArrayParser::class, // parce method toArray in resource
    FillableParser::class, // parcel model with protect $fillable = []
],

```


For older versions of Laravel, automatic package discovery will handle this for you.

## Usage

Once the package is installed and configured, it will automatically generate TypeScript interfaces from your Laravel Resource files. These interfaces can then be utilized in your TypeScript-based frontend projects for better type safety and code consistency.

#### Run command for generate typescript interfaces
```shell
php artisan diephp:generate-typescript-interfaces
```

## Examples

Assume you have a Laravel Resource like this:




### 1 standard resource without declaration
```php
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ExampleResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
        ];
    }
}
```
The package will generate a corresponding TypeScript interface:
```typescript
export interface ExampleResource {
    id: any;
    name: any;
}
```
This generated file can now be used in your frontend structure.



### 2  resource with ArrayShape
```php
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ExampleResource extends JsonResource
{
    #[ArrayShape(['id' => "int", 'name' => "string"])] public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
        ];
    }
}
```
The package will generate a corresponding TypeScript interface:
```typescript
export interface ExampleResource {
    id: number;
    name: string;
}
```
This generated file can now be used in your frontend structure.


### 3  resource with PHPDOC
```php
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ExampleResource extends JsonResource
{
    /**
     * @return array{
     *     id: int,
     *     name: string,
     * }
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
        ];
    }
}
```
The package will generate a corresponding TypeScript interface:
```typescript
export interface ExampleResource {
    id: number;
    name: string;
}
```
This generated file can now be used in your frontend structure.


### 4  resource simple toArray with types
```php
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ExampleResource extends JsonResource
{

    public function toArray($request)
    {
        return [
            'id' => (int) $this->id,
            'name' => (string) $this->name,
        ];
    }
}
```
The package will generate a corresponding TypeScript interface:
```typescript
export interface ExampleResource {
    id: number;
    name: string;
}
```
This generated file can now be used in your frontend structure.


## Contributing

If you would like to contribute, feel free to submit a pull request or file an issue on the [GitHub repository](https://github.com/diephp/laravel-resources-typescript).

## License

This package is licensed under the MIT License. See the [LICENSE](LICENSE) file for more details.