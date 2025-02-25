<?php

namespace DiePHP\Tests\Examples;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AddressResource extends JsonResource
{

    /**
     * @return array{
     *     street: \DiePHP\Tests\Examples\StreetResource,
     *     number: string,
     *     zip: string,
     *     zip2: mixed,
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'street' => new StreetResource($this->resource->street),
            'number' => (string)$this->resource->name,
            'zip' => (int)$this->resource->zip,
        ];
    }

}
