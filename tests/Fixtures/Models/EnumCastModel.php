<?php

namespace LaravelSpectrum\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use LaravelSpectrum\Tests\Fixtures\Enums\PriorityEnum;
use LaravelSpectrum\Tests\Fixtures\Enums\StatusEnum;

class EnumCastModel extends Model
{
    protected $casts = [
        'status' => StatusEnum::class,
        'priority' => PriorityEnum::class,
    ];
}
