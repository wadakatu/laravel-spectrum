<?php

namespace App\Http\Controllers;

use App\Http\Requests\FileUploadDemoRequest;
use Illuminate\Http\JsonResponse;

class FileUploadController extends Controller
{
    /**
     * Handle user profile upload
     *
     * This endpoint handles file uploads for user profiles including avatar, resume and certificates
     */
    public function upload(FileUploadDemoRequest $request): JsonResponse
    {
        // Handle file uploads
        $data = $request->validated();

        // Process files (in real app, you would save these)
        $response = [
            'message' => 'Files uploaded successfully',
            'data' => [
                'name' => $data['name'],
                'email' => $data['email'],
                'avatar_uploaded' => $request->hasFile('avatar'),
                'resume_uploaded' => $request->hasFile('resume'),
                'certificates_count' => count($request->file('certificates') ?? []),
            ],
        ];

        return response()->json($response, 201);
    }

    /**
     * Upload multiple images
     *
     * This endpoint handles multiple image uploads with inline validation
     */
    public function uploadImages()
    {
        $validated = request()->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'images' => 'required|array|min:1|max:10',
            'images.*' => 'required|image|mimes:jpeg,png,gif|max:5120|dimensions:min_width=100,min_height=100',
            'thumbnail' => 'required|image|max:1024|dimensions:ratio=16/9',
        ]);

        return response()->json([
            'message' => 'Images uploaded successfully',
            'data' => [
                'title' => $validated['title'],
                'images_count' => count($validated['images']),
                'thumbnail_uploaded' => request()->hasFile('thumbnail'),
            ],
        ], 201);
    }
}
