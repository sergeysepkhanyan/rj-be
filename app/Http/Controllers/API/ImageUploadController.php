<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;

class ImageUploadController extends Controller
{
    public function upload(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'slug' => 'required|string',
                'image' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            ]);

            if ($validator->fails()) {
                return ApiResponse::error($validator->errors(), 'Validation failed', 422);
            }
            $slug = $request->get('slug');
            $path = 'images/' . $slug;
            $image = $request->file('image')->store($path, 'public');
            return ApiResponse::success(['image' => $image], 'Image uploaded successfully');
        } catch (\Exception $e){
            return ApiResponse::error();
        }
    }
}
