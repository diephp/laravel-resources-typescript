<?php

declare(strict_types=1);

namespace DiePHP\Tests\Examples;

use Illuminate\Http\Resources\Json\JsonResource;

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
