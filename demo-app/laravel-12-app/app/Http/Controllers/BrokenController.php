<?php

namespace App\Http\Controllers;

use App\Http\Requests\BrokenFormRequest;

class BrokenController extends Controller
{
    public function brokenEndpoint(BrokenFormRequest $request)
    {
        return response()->json(['message' => 'This endpoint uses a broken form request']);
    }

    public function brokenResource()
    {
        return \App\Http\Resources\BrokenResource::collection([]);
    }
}
