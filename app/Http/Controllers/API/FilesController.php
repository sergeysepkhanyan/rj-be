<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\UploadFileRequest;
use App\Http\Requests\UploadMultipleFilesRequest;
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
    public function upload(UploadFileRequest $request): JsonResponse
    {
        $slug = $request->get('slug');
        $imagePath = $this->fileService->upload($request->file('image'), $slug);

        return ApiResponse::success(['image' => $imagePath], 'Image uploaded successfully');
    }

    public function uploadMultiple(UploadMultipleFilesRequest $request): JsonResponse
    {
        $slug = $request->get('slug');
        $paths = $this->fileService->uploadMultiple($request->file('images'), $slug);

        return ApiResponse::success(['images' => $paths], 'Images uploaded successfully');
    }
}
