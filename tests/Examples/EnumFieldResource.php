<?php

declare(strict_types=1);

namespace DiePHP\Tests\Examples;

use Illuminate\Http\Resources\Json\JsonResource;

class EnumFieldResource extends JsonResource
{
    public function toArray($request): array
    {
        $payload = [];
        $payload['status'] = StatusEnum::Published;
        $payload['statusFromFactory'] = StatusEnum::from('draft');
        $payload['nullableStatus'] = null;

        if ($this->resource?->show_previous ?? false) {
            $payload['previousStatus'] = StatusEnum::Archived;
        }

        return $payload;
    }
}
