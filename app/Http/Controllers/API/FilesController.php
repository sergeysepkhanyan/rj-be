<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\ApiResponse;
use App\Services\FileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;

class FilesController extends Controller
{
    public function __construct(protected FileService $fileService)
    {
        $this->fileService = $fileService;
    }
    public function upload(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'slug' => 'required|string',
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error($validator->errors(), 'Validation failed', 422);
        }

        try {
            $slug = $request->get('slug');

            $imagePath = $this->fileService->upload($request->file('image'), $slug);

            return ApiResponse::success(['image' => $imagePath], 'Image uploaded successfully');
        } catch (\Exception $e) {
            return ApiResponse::error();
        }
    }

    public function uploadMultiple(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'slug' => 'required|string',
            'images' => 'required|array',
            'images.*' => 'required|file|mimes:jpeg,png,jpg,gif,svg,pdf,doc,docx|max:2048',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error($validator->errors(), 'Validation failed', 422);
        }

        try {
            $slug = $request->get('slug');
            $paths = $this->fileService->uploadMultiple($request->file('images'), $slug);

            return ApiResponse::success(['images' => $paths], 'Images uploaded successfully');
        } catch (\Exception $e) {
            return ApiResponse::error();
        }
    }
}
