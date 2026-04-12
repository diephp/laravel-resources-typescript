<?php

declare(strict_types=1);

namespace DiePHP\Tests\Examples;

use Illuminate\Http\Resources\Json\JsonResource;

class AnalyticsShapeResource extends JsonResource
{
    /**
     * @return array{
     *     series: array<int, object{name: string, data: array<int, int>}>,
     *     categories: array<int, string>
     * }
     */
    public function toArray($request): array
    {
        return [
            'series' => [],
            'categories' => [],
        ];
    }
}
