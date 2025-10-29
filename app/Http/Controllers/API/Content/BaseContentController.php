<?php

namespace App\Http\Controllers\API\Content;

use App\Http\Controllers\Controller;
use App\Services\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\App;

abstract class BaseContentController extends Controller
{
    /**
     * Get localized content for a specific page key (e.g., home, about).
     */
    protected function getPageContent(string $pageKey): JsonResponse
    {
        $locale = request('locale', App::getLocale());
        App::setLocale($locale);
        $content = __('contentc');
        if (!isset($content[$pageKey])) {
            return ApiResponse::error([
                "$pageKey" => "Page '{$pageKey}' not found in content file for locale '{$locale}'.",
            ], 'Not Found', 404);
        }
        return ApiResponse::success(
            [
                'locale' => $locale,
                'page' => $pageKey,
                'content' => $content[$pageKey],
            ],
            "Content for $pageKey page."
        );
    }
}

