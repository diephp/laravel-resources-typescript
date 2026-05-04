<?php

declare(strict_types=1);

namespace DiePHP\Tests\Examples;

use Illuminate\Http\Resources\Json\JsonResource;

class ApiMediaResource extends JsonResource
{
    /**
     * @return array{
     *     id: int,
     *     type: string,
     *     thumbnails: object{
     *         big: ?string,
     *         mini: ?string,
     *         small: ?string,
     *         medium: ?string,
     *         p320: ?string,
     *         p720: ?string
     *     },
     *     created_at: string
     * }
     */
    public function toArray($request): array
    {
        return [
            'id' => (int) $this->resource->id,
            'type' => (string) $this->resource->type,
            'thumbnails' => (array) $this->resource->thumbnails,
            'created_at' => (string) $this->resource->created_at,
        ];
    }
}
