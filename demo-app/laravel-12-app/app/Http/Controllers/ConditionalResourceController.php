<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\ConditionalUserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Controller testing conditional resource patterns.
 */
class ConditionalResourceController extends Controller
{
    /**
     * Return user with conditional relationships based on include parameter.
     */
    public function show(Request $request, int $id): ConditionalUserResource
    {
        $validated = $request->validate([
            'include' => 'nullable|string',
            'include_internal' => 'nullable|boolean',
            'full' => 'nullable|boolean',
        ]);

        $query = User::query();

        // Parse include parameter (JSON API style)
        if (isset($validated['include'])) {
            $includes = explode(',', $validated['include']);
            $allowedIncludes = ['posts', 'comments', 'profile'];
            $validIncludes = array_intersect($includes, $allowedIncludes);

            if (!empty($validIncludes)) {
                $query->with($validIncludes);
            }
        }

        $user = $query->findOrFail($id);

        return new ConditionalUserResource($user);
    }

    /**
     * List users with optional relationship counts.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'with_counts' => 'nullable|string',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = User::query();

        // Add counts if requested
        if (isset($validated['with_counts'])) {
            $counts = explode(',', $validated['with_counts']);
            $allowedCounts = ['posts', 'comments'];
            $validCounts = array_intersect($counts, $allowedCounts);

            if (!empty($validCounts)) {
                $query->withCount($validCounts);
            }
        }

        $users = $query->paginate($validated['per_page'] ?? 15);

        return ConditionalUserResource::collection($users);
    }
}
