<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

class Issue458Project extends Model
{
    /**
     * @var array<string, string>
     */
    protected $casts = [
        'notification_codes' => 'array',
        'verified' => 'integer',
    ];
}
