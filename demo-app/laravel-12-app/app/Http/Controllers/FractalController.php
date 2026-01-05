<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\User;
use App\Transformers\PostTransformer;
use App\Transformers\UserTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use League\Fractal\Manager;
use League\Fractal\Pagination\IlluminatePaginatorAdapter;
use League\Fractal\Resource\Collection;
use League\Fractal\Resource\Item;
use League\Fractal\Serializer\ArraySerializer;

/**
 * Controller demonstrating Fractal Transformer integration.
 *
 * This controller showcases various Fractal features:
 * - Basic item transformation
 * - Collection transformation with pagination
 * - Include parsing (sparse fieldsets)
 * - Different serializers
 */
class FractalController extends Controller
{
    private Manager $fractal;

    public function __construct()
    {
        $this->fractal = new Manager;
        $this->fractal->setSerializer(new ArraySerializer);
    }

    /**
     * List users with Fractal transformation.
     *
     * Supports pagination and includes via query parameters.
     * Example: ?include=posts,profile&page=1&per_page=15
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'include' => 'nullable|string',
        ]);

        // Parse includes if provided
        if ($request->has('include')) {
            $this->fractal->parseIncludes($request->input('include'));
        }

        $perPage = $validated['per_page'] ?? 15;
        $paginator = User::paginate($perPage);

        $resource = new Collection($paginator->items(), new UserTransformer);
        $resource->setPaginator(new IlluminatePaginatorAdapter($paginator));

        $data = $this->fractal->createData($resource)->toArray();

        return response()->json($data);
    }

    /**
     * Show a single user with Fractal transformation.
     *
     * Example: ?include=posts,profile
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'include' => 'nullable|string',
        ]);

        if ($request->has('include')) {
            $this->fractal->parseIncludes($request->input('include'));
        }

        $user = User::findOrFail($id);

        $resource = new Item($user, new UserTransformer);
        $data = $this->fractal->createData($resource)->toArray();

        return response()->json($data);
    }

    /**
     * List posts with Fractal transformation.
     *
     * Demonstrates nested includes: ?include=author,comments,tags
     */
    public function posts(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'include' => 'nullable|string',
            'published_only' => 'nullable|boolean',
        ]);

        if ($request->has('include')) {
            $this->fractal->parseIncludes($request->input('include'));
        }

        $query = Post::query();

        if ($request->boolean('published_only')) {
            $query->where('is_published', true);
        }

        $perPage = $validated['per_page'] ?? 15;
        $paginator = $query->paginate($perPage);

        $resource = new Collection($paginator->items(), new PostTransformer);
        $resource->setPaginator(new IlluminatePaginatorAdapter($paginator));

        $data = $this->fractal->createData($resource)->toArray();

        return response()->json($data);
    }

    /**
     * Show a single post with Fractal transformation.
     *
     * Author is included by default. Additional includes: comments, tags
     */
    public function showPost(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'include' => 'nullable|string',
        ]);

        if ($request->has('include')) {
            $this->fractal->parseIncludes($request->input('include'));
        }

        $post = Post::findOrFail($id);

        $resource = new Item($post, new PostTransformer);
        $data = $this->fractal->createData($resource)->toArray();

        return response()->json($data);
    }

    /**
     * Get user's posts using nested resource pattern.
     *
     * Demonstrates accessing related resources through parent.
     */
    public function userPosts(Request $request, int $userId): JsonResponse
    {
        $validated = $request->validate([
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:50',
            'include' => 'nullable|string',
        ]);

        if ($request->has('include')) {
            $this->fractal->parseIncludes($request->input('include'));
        }

        $user = User::findOrFail($userId);
        $perPage = $validated['per_page'] ?? 10;
        $paginator = $user->posts()->paginate($perPage);

        $resource = new Collection($paginator->items(), new PostTransformer);
        $resource->setPaginator(new IlluminatePaginatorAdapter($paginator));

        $data = $this->fractal->createData($resource)->toArray();

        return response()->json([
            'user_id' => $userId,
            'posts' => $data,
        ]);
    }
}
