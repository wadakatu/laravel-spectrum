<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FileUploadController extends Controller
{
    /**
     * Upload profile image
     */
    public function upload(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'avatar' => 'required|image|max:2048',
            'description' => 'string|max:255',
        ]);

        return response()->json([
            'message' => 'Profile image uploaded successfully',
            'path' => '/uploads/profile/'.uniqid().'.jpg',
        ]);
    }

    /**
     * Upload multiple images
     */
    public function uploadImages(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'images' => 'required|array',
            'images.*' => 'image|max:2048',
        ]);

        return response()->json([
            'message' => 'Images uploaded successfully',
            'count' => count($validated['images']),
        ]);
    }

    /**
     * Upload gallery images
     */
    public function uploadGallery(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'photos' => 'required|array|min:1|max:10',
            'photos.*' => 'image|mimes:jpeg,png,jpg|max:5120',
        ]);

        return response()->json([
            'message' => 'Gallery created successfully',
            'gallery_id' => uniqid(),
        ]);
    }

    /**
     * Upload documents
     */
    public function uploadDocuments(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'documents' => 'required|array',
            'documents.*' => 'file|mimes:pdf,doc,docx|max:10240',
        ]);

        return response()->json([
            'message' => 'Documents uploaded successfully',
            'count' => count($validated['documents']),
        ]);
    }
}
