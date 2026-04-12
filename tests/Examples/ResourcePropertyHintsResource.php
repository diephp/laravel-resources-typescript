<?php

declare(strict_types=1);

namespace DiePHP\Tests\Examples;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * @property array $name
 * @property StatusEnum $status
 * @property Collection $categories
 * @property string $unused
 */
class ResourcePropertyHintsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'name' => $this->resource->name,
            'status' => $this->resource->status,
            'categories' => $this->resource->categories,
        ];
    }
}
