<?php

declare(strict_types=1);

namespace DiePHP\Tests\Examples;

use Illuminate\Http\Resources\Json\JsonResource;
use JetBrains\PhpStorm\ArrayShape;

class StreetResource extends JsonResource
{
    #[ArrayShape(['name' => 'string', 'number' => 'int'])]
    public function toArray($request): array
    {
        return [
            'name' => $this->resource->id,
            'number' => $this->resource->name,
        ];
    }
}
