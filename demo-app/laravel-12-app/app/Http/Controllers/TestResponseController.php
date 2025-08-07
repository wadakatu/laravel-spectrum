<?php

namespace App\Http\Controllers;

use App\Models\User;

class TestResponseController extends Controller
{
    /**
     * response()->json()のテスト
     */
    public function responseJson()
    {
        $user = User::find(1);

        return response()->json([
            'data' => $user->only(['id', 'name', 'email']),
            'meta' => [
                'version' => '1.0',
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * 配列直接返却のテスト
     */
    public function arrayReturn()
    {
        return [
            'status' => 'success',
            'total' => 100,
            'page' => 1,
        ];
    }

    /**
     * Eloquentモデル返却のテスト
     */
    public function modelReturn($id)
    {
        return User::findOrFail($id);
    }

    /**
     * コレクション操作のテスト
     */
    public function collectionMap()
    {
        return User::all()->map(function ($user) {
            return [
                'id' => $user->id,
                'display_name' => $user->name,
                'contact' => $user->email,
            ];
        });
    }
}
