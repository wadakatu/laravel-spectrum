<?php

namespace LaravelSpectrum\Tests\Fixtures\Enums;

enum TagEnum: string
{
    case FEATURED = 'featured';
    case POPULAR = 'popular';
    case NEW = 'new';
    case TRENDING = 'trending';
}
