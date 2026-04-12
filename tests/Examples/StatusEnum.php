<?php

declare(strict_types=1);

namespace DiePHP\Tests\Examples;

enum StatusEnum: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
