<?php

declare(strict_types=1);

namespace DiePHP\Tests\Examples;

use Illuminate\Http\Resources\Json\JsonResource;

class AddressResource extends JsonResource
{
    /**
     * @return array{
     *     street: \DiePHP\Tests\Examples\StreetResource,
     *     number: string,
     *     zip: int,
     *     zip2: mixed,
     * }
     */
    public function toArray($request): array
    {
        return [
            'street' => new StreetResource($this->resource->street),
            'number' => (string) $this->resource->name,
            'zip' => (int) $this->resource->zip,
        ];
    }
}
