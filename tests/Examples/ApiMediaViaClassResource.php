<?php

declare(strict_types=1);

namespace DiePHP\Tests\Examples;

use DiePHP\Tests\Support\MediaThumbnails;
use Illuminate\Http\Resources\Json\JsonResource;

class ApiMediaViaClassResource extends JsonResource
{
    /**
     * @return array{
     *     id: int,
     *     thumbnails: MediaThumbnails,
     *     created_at: string
     * }
     */
    public function toArray($request): array
    {
        return [
            'id' => (int) $this->resource->id,
            'thumbnails' => (array) $this->resource->thumbnails,
            'created_at' => (string) $this->resource->created_at,
        ];
    }
}
