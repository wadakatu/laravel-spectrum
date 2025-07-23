<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class RequestValidateController extends Controller
{
    /**
     * Store a new blog post using request()->validate()
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validated = request()->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string|min:10',
            'author' => 'required|string|max:100',
            'published' => 'required|boolean',
            'tags' => 'array|max:5',
            'tags.*' => 'string|max:50',
        ], [
            'title.required' => 'The blog title is required.',
            'content.required' => 'The blog content is required.',
            'content.min' => 'The blog content must be at least 10 characters.',
        ], [
            'title' => 'Blog Title',
            'content' => 'Blog Content',
            'author' => 'Author Name',
        ]);

        return response()->json([
            'message' => 'Blog post created successfully',
            'data' => $validated,
        ], 201);
    }

    /**
     * Upload files using request()->validate()
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function upload(Request $request)
    {
        $validated = request()->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg|max:2048',
            'resume' => 'nullable|file|mimes:pdf,doc,docx|max:5120',
            'portfolio' => 'nullable|url',
        ]);

        return response()->json([
            'message' => 'Files uploaded successfully',
            'uploaded' => array_keys($validated),
        ]);
    }

    /**
     * Update user profile with mixed validation
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        // First validate the user ID
        $this->validate($request, [
            'user_id' => 'required|integer|exists:users,id',
        ]);

        // Then validate the profile data using request()->validate()
        $profileData = request()->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,'.$id,
            'bio' => 'nullable|string|max:500',
            'website' => 'nullable|url',
            'profile_image' => 'sometimes|image|max:1024',
        ]);

        return response()->json([
            'message' => 'Profile updated successfully',
            'updated_fields' => array_keys($profileData),
        ]);
    }
}
