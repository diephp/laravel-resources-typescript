<?php

namespace DiePHP\Tests\Examples;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TimeResource extends JsonResource
{

    public function toArray(Request $request): array
    {
        $res = [];
        $res['time'] = $this->resource->time;
        if ($this->resource->time_zone) {
            $res['time_zone'] = $this->resource->time_zone;
        }
        return $res;
    }

}
