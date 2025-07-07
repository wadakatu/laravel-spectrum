<?php

namespace LaravelSpectrum\Tests\Fixtures\Controllers;

use LaravelSpectrum\Tests\Fixtures\StoreUserRequest;
use LaravelSpectrum\Tests\Fixtures\UserResource;

class UserController
{
    public function index() {}

    public function store(StoreUserRequest $request)
    {
        return new UserResource([]);
    }

    public function show($user)
    {
        return new UserResource([]);
    }

    public function update($post, $comment = null) {}
}
