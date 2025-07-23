<?php

namespace LaravelSpectrum\Tests\Fixtures\Enums;

enum UserTypeEnum: string
{
    case ADMIN = 'admin';
    case USER = 'user';
    case GUEST = 'guest';
}
