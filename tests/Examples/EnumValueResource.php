<?php

declare(strict_types=1);

namespace DiePHP\Tests\Examples;

use Illuminate\Http\Resources\Json\JsonResource;

class EnumValueResource extends JsonResource
{
    public function toArray($request)
    {
        return StatusEnum::Published;
    }
}
