<?php

namespace LaravelSpectrum\Tests\Fixtures\Controllers;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Http\Request;
use LaravelSpectrum\Tests\Fixtures\Models\Comment;
use LaravelSpectrum\Tests\Fixtures\Models\Post;
use LaravelSpectrum\Tests\Fixtures\Models\User;
use LaravelSpectrum\Tests\Fixtures\Resources\UserResource;

class PaginatedController
{
    public function lengthAwarePagination()
    {
        return User::paginate(15);
    }

    public function simplePagination()
    {
        return Post::simplePaginate(10);
    }

    public function cursorPagination()
    {
        return Comment::cursorPaginate(20);
    }

    public function withResource()
    {
        return UserResource::collection(User::paginate());
    }

    public function withQueryBuilder()
    {
        return User::where('status', 'active')
            ->orderBy('created_at', 'desc')
            ->paginate();
    }

    public function withCustomPerPage(Request $request)
    {
        return User::paginate($request->input('per_page', 20));
    }

    public function withReturnType(): LengthAwarePaginator
    {
        return User::paginate();
    }

    public function withSimplePaginatorReturnType(): Paginator
    {
        return User::simplePaginate();
    }

    public function withCursorPaginatorReturnType(): CursorPaginator
    {
        return User::cursorPaginate();
    }

    public function nonPaginated()
    {
        return User::all();
    }

    public function conditionalPagination(Request $request)
    {
        if ($request->wants_all) {
            return User::all();
        }

        return User::paginate();
    }

    public function relationPagination(User $user)
    {
        return $user->posts()->paginate();
    }

    public function customPaginationResponse()
    {
        $users = User::paginate();

        return [
            'users' => UserResource::collection($users),
            'pagination' => [
                'total' => $users->total(),
                'current_page' => $users->currentPage(),
            ],
        ];
    }
}
