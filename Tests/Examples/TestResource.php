<?php

namespace DiePHP\Tests\Examples;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class TestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' =>      (int)$this->resource->id,
            'uuid' => Arr::get($this->resource, 'uuid'),
            'type' =>     (string)$this->resource->type,
            'user' => new UserDto(),
            'contact' => new ContactModel(),
            'category' => new CategoryResource($this->resource->category),
            'size' => (int)         $this->resource->size,
            'list' => (array)$this->resource->thumbnails->toArray() ?? [],
            'address' => AddressResource::collection($this->resource->address),
            'site' => new SiteModel(),
            'createdAt' => new \DiePHP\Tests\Examples\TimeResource($this->resource->created_at)
        ];
    }

}
