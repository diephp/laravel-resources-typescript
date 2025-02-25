<?php

namespace DiePHP\Tests\Examples;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' =>      (int)$this->resource->id,
            'name' =>     (string)$this->resource->name,
        ];
    }

}
