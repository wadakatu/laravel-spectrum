<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use LaravelSpectrum\Tests\Fixtures\Enums\StatusEnum;

/**
 * Model with enum cast using format 'EnumClass:type'.
 */
class EnumCastFormatModel extends Model
{
    protected $casts = [
        // Using the EnumClass:type format
        'status' => StatusEnum::class.':string',
    ];
}
