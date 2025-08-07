<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;

class PaginationTestController extends Controller
{
    /**
     * Display a paginated list of users.
     */
    public function index(Request $request)
    {
        return User::paginate($request->input('per_page', 15));
    }

    /**
     * Display a paginated list of users with resource.
     */
    public function withResource()
    {
        return UserResource::collection(User::paginate());
    }

    /**
     * Display a simple paginated list of users.
     */
    public function simplePagination()
    {
        return User::simplePaginate(10);
    }

    /**
     * Display a cursor paginated list of users.
     */
    public function cursorPagination()
    {
        return User::cursorPaginate(20);
    }

    /**
     * Display users with query builder pagination.
     */
    public function withQueryBuilder()
    {
        return User::where('email_verified_at', '!=', null)
            ->orderBy('created_at', 'desc')
            ->paginate();
    }
}
